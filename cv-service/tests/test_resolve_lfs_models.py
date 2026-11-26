import hashlib
import io
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from resolve_lfs_models import resolve_models


class ResolveModelsTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.models = Path(self.directory.name)
        self.model = self.models / "trotoar_classifier.onnx"
        self.binary = b"example model bytes"
        self.oid = hashlib.sha256(self.binary).hexdigest()
        self.pointer = (
            f"version https://git-lfs.github.com/spec/v1\n"
            f"oid sha256:{self.oid}\nsize {len(self.binary)}\n"
        ).encode()

    def batch_response(self, **extra):
        return io.BytesIO(json.dumps({"objects": [{"oid": self.oid, **extra}]}).encode())

    @patch("resolve_lfs_models.urllib.request.urlopen")
    def test_materialized_model_needs_no_network(self, urlopen):
        self.model.write_bytes(self.binary)
        resolve_models(self.models, "https://example.com/info/lfs")
        urlopen.assert_not_called()
        self.assertEqual(self.model.read_bytes(), self.binary)

    @patch("resolve_lfs_models.urllib.request.urlopen")
    def test_pointer_download_uses_exact_oid_and_action_headers(self, urlopen):
        self.model.write_bytes(self.pointer)
        urlopen.side_effect = [
            self.batch_response(actions={"download": {
                "href": "https://example.com/model",
                "header": {"Authorization": "signed-download"},
            }}),
            io.BytesIO(self.binary),
        ]
        resolve_models(self.models, "https://example.com/info/lfs")
        self.assertEqual(self.model.read_bytes(), self.binary)
        batch_request = urlopen.call_args_list[0].args[0]
        self.assertEqual(json.loads(batch_request.data)["objects"], [
            {"oid": self.oid, "size": len(self.binary)},
        ])
        download_request = urlopen.call_args_list[1].args[0]
        self.assertEqual(download_request.get_header("Authorization"), "signed-download")

    @patch("resolve_lfs_models.urllib.request.urlopen")
    def test_bad_digest_or_size_preserves_pointer_and_removes_partial_file(self, urlopen):
        for corrupt in (b"x" * len(self.binary), b"short", self.binary + b"extra"):
            with self.subTest(corrupt=corrupt):
                self.model.write_bytes(self.pointer)
                urlopen.side_effect = [
                    self.batch_response(actions={"download": {"href": "https://example.com/model"}}),
                    io.BytesIO(corrupt),
                ]
                with self.assertRaisesRegex(RuntimeError, "mismatch"):
                    resolve_models(self.models, "https://example.com/info/lfs")
                self.assertEqual(self.model.read_bytes(), self.pointer)
                self.assertFalse(self.model.with_suffix(".onnx.download").exists())

    @patch("resolve_lfs_models.urllib.request.urlopen")
    def test_missing_remote_object_fails_clearly(self, urlopen):
        self.model.write_bytes(self.pointer)
        urlopen.return_value = self.batch_response(error={"code": 404, "message": "not found"})
        with self.assertRaisesRegex(RuntimeError, "download unavailable"):
            resolve_models(self.models, "https://example.com/info/lfs")
        self.assertEqual(self.model.read_bytes(), self.pointer)

    def test_empty_models_directory_fails(self):
        with self.assertRaisesRegex(RuntimeError, "No ONNX models"):
            resolve_models(self.models, "https://example.com/info/lfs")
