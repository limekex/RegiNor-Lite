"""Local public HTTP checks with fixture cleanup and option restoration."""
import base64,json,subprocess,sys,tempfile
from pathlib import Path
root=Path(__file__).resolve().parent.parent
cmd=[str(root/'node_modules/.bin/wp-env'),'run','cli','wp','eval-file','/var/www/html/rnl-tests/http-fixtures.php']
setup=subprocess.run(cmd+['public'],cwd=root,text=True,capture_output=True,check=True)
fixtures=next(json.loads(line) for line in setup.stdout.splitlines() if line.startswith('{'))
with tempfile.TemporaryDirectory(prefix='rnl-public-') as folder:
    path=Path(folder)/'fixtures.json';path.write_text(json.dumps(fixtures))
    try: subprocess.run([sys.executable,str(root/'tests/http-public.py'),str(path)],cwd=root,check=True)
    finally:
        cleanup={key:fixtures[key] for key in ['created','user','old_types','types_existed','old_page','capacity_cron_existed']}
        subprocess.run(cmd+['cleanup',base64.b64encode(json.dumps(cleanup).encode()).decode()],cwd=root,check=True,capture_output=True)
        print('Offentlige HTTP-testobjekter er ryddet bort.')
