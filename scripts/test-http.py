"""Run local HTTP checks and always remove this run's synthetic fixtures."""
import base64
import json
from pathlib import Path
import subprocess
import sys
import tempfile

root = Path(__file__).resolve().parent.parent
command = [str(root / 'node_modules/.bin/wp-env'), 'run', 'cli', 'wp', 'eval-file', '/var/www/html/rnl-tests/http-fixtures.php']
setup = subprocess.run(command, cwd=root, text=True, capture_output=True, check=True)
fixtures = next(json.loads(line) for line in setup.stdout.splitlines() if line.startswith('{'))
with tempfile.TemporaryDirectory(prefix='rnl-http-') as folder:
    path = Path(folder) / 'fixtures.json'
    path.write_text(json.dumps(fixtures))
    try:
        subprocess.run([sys.executable, str(root / 'tests/http-workflow.py'), str(path)], cwd=root, check=True)
    finally:
        fixtures = json.loads(path.read_text())
        cleanup = {key: fixtures[key] for key in ['created', 'user', 'old_types', 'types_existed', 'capacity_cron_existed']}
        subprocess.run(command + ['cleanup', base64.b64encode(json.dumps(cleanup).encode()).decode()], cwd=root, check=True, capture_output=True)
        print('HTTP-testobjekter er ryddet bort.')
