'use strict';

const fs = require('node:fs');
const path = require('node:path');

// Exclusive creation preserves existing credentials and unrelated wp-env overrides.
function configure(root) {
    const destination = path.join(root, '.wp-env.override.json');
    const template = fs.readFileSync(path.join(root, '.wp-env.override.example.json'));
    try {
        fs.writeFileSync(path.join(root, '.env.letsreg.json'), fs.readFileSync(path.join(root, '.env.example')), { flag: 'wx', mode: 0o600 });
    } catch (error) { if (error.code !== 'EEXIST') throw error; }
    try {
        fs.writeFileSync(destination, template, { flag: 'wx', mode: 0o600 });
        return 'Oppsett klart. Fyll inn LetsReg-verdiene i .env.letsreg.json, og kjør npm run env:start -- --update. Eksisterende tilgangsfil er bevart.';
    } catch (error) {
        if (error.code !== 'EEXIST') throw error;
        return '.wp-env.override.json finnes allerede og er bevart. Legg eventuelt til mappings fra eksempelmalen. Tilgangen fylles inn i .env.letsreg.json.';
    }
}

if (require.main === module) console.log(configure(path.resolve(__dirname, '..')));
module.exports = { configure };
