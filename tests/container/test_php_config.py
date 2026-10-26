#!/usr/bin/env python3
"""Source regression checks; run from any directory with Python 3."""
import configparser
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class PHPContainerConfig(unittest.TestCase):
    def test_supported_runtime(self):
        for variant in ('alpine', 'dev'):
            with self.subTest(variant=variant):
                dockerfile = (ROOT / 'docker' / variant / 'Dockerfile').read_text()
                self.assertIn('FROM alpine:3.24', dockerfile)
                self.assertNotRegex(dockerfile, r'https?://[^\s"]+/alpine/v[\d.]+/')

    def test_php_version_guard(self):
        for variant in ('alpine', 'dev'):
            with self.subTest(variant=variant):
                dockerfile = (ROOT / 'docker' / variant / 'Dockerfile').read_text()
                self.assertIn("php83 -r 'exit(PHP_VERSION_ID >= 80336 && PHP_VERSION_ID < 80400 ? 0 : 1);'", dockerfile)

    def test_upload_progress_disabled_without_changing_limits(self):
        for variant in ('alpine', 'dev'):
            with self.subTest(variant=variant):
                ini = configparser.ConfigParser(interpolation=None)
                ini.read(ROOT / 'docker' / variant / 'php.ini')
                self.assertEqual(ini['PHP'].get('session.upload_progress.enabled', '').lower(), 'off')
                for setting in ('post_max_size', 'upload_max_filesize', 'memory_limit'):
                    self.assertEqual(ini['PHP'][setting], '4G')
                dockerfile = (ROOT / 'docker' / variant / 'Dockerfile').read_text()
                self.assertRegex(dockerfile, r'COPY .*php.ini /etc/php83/conf.d/99-erugo.ini')


if __name__ == '__main__':
    unittest.main()
