"""Synthetic tests for non-overwriting local configuration initialization."""
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest


class InitTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="esd-init-test-")
        self.root = Path(self.temporary.name)
        (self.root / "bin").mkdir()
        self.script = self.root / "bin/init-local"
        shutil.copyfile(Path(__file__).resolve().parents[2] / "bin/init-local", self.script)

    def tearDown(self):
        self.temporary.cleanup()

    def run_init(self):
        return subprocess.run([sys.executable, str(self.script)], capture_output=True, text=True, check=False)

    def test_generation_and_byte_identical_second_run(self):
        first = self.run_init()
        self.assertEqual(first.returncode, 0)
        target = self.root / ".env.local"
        original = target.read_bytes()
        self.assertEqual(target.stat().st_mode & 0o777, 0o600)
        self.assertNotIn(original.decode(), first.stdout)
        self.assertEqual(self.run_init().returncode, 0)
        self.assertEqual(target.read_bytes(), original)

    def test_existing_custom_configuration_is_preserved(self):
        target = self.root / ".env.local"
        target.write_text("CUSTOM=synthetic\n")
        target.chmod(0o600)
        self.assertEqual(self.run_init().returncode, 0)
        self.assertEqual(target.read_text(), "CUSTOM=synthetic\n")

    def test_symlink_is_rejected_without_touching_target(self):
        outside = self.root / "synthetic-target"
        outside.write_text("preserve")
        (self.root / ".env.local").symlink_to(outside)
        self.assertNotEqual(self.run_init().returncode, 0)
        self.assertEqual(outside.read_text(), "preserve")

    def test_permissive_existing_file_is_rejected(self):
        target = self.root / ".env.local"
        target.write_text("preserve")
        target.chmod(0o644)
        self.assertNotEqual(self.run_init().returncode, 0)
        self.assertEqual(target.read_text(), "preserve")


if __name__ == "__main__":
    unittest.main()
