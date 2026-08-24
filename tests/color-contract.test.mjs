import assert from 'node:assert/strict'; import fs from 'node:fs'; import test from 'node:test';
const css = fs.readFileSync(new URL('../blocks/todays-readings/style.css', import.meta.url), 'utf8');
test('readings use shared structural colors while retaining liturgical colors', () => { for (const role of ['surface','foreground','text-muted','border']) assert.match(css, new RegExp(`--mc-color-${role}`)); for (const liturgical of ['#26734d','#a12424','#6d477e','#b55c78','#c5a24a']) assert.match(css, new RegExp(liturgical)); });
