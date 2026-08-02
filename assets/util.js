// @ts-check

import { createSignal } from "solid-js";
import html from "solid-js/html";
/** @import { Signal, Setter } from 'solid-js' */
/** @import { StandardSchemaV1 } from './types.ts' */

/** @template T @param {string} key @param {T} defaultValue @param {StandardSchemaV1<T>} schema  @returns {Signal<T>} */
export function createStorageSignal(key, defaultValue, storage = localStorage, schema) {
    const json = storage.getItem(key);
    if (json) {
        try {
            const result = schema['~standard'].validate(JSON.parse(json));
            if (result instanceof Promise) { throw 'unsupported'; };
            if (!result.issues) {
                defaultValue = result.value;
            } else {
                console.warn(JSON.stringify(result.issues));
            }
        } catch (ex) {
            console.warn(ex);
        }
    }
    const [value, setValue] = createSignal(defaultValue);

    /** @param {Exclude<T, Function> | ((v:T) => T)} newValue */
    const setStorageValue = (newValue) => {
        const v = setValue(newValue);
        storage.setItem(key, JSON.stringify(v));
        return v;
    };
    return [value, /** @type {Setter<T>} */(setStorageValue)];
}

/** @param {string} text @param {RegExp | undefined} reHighlight */
export function highlight(text, reHighlight) {
    if (reHighlight) {
        /** @type {Array<Node | Node[]>} */ const result = [];
        let p = 0;
        for (const a of text.matchAll(reHighlight)) {
            if (p < a.index) {
                //word before highlight
                result.push(html`<span>${text.substring(p, a.index)}</span>`);
            }
            //highlighted word
            p = a.index + a[0].length;
            result.push(html`<mark>${text.substring(a.index, p)}</mark>`);
        }
        if (p) {
            //rest
            if (p < text.length) {
                result.push(html`<span>${text.substring(p)}</span>`);
            }
            return result;
        }
    }
    //no highlight
    return text;
}

/** @param {string} text */
export async function toClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch (_err) {
        const el = document.createElement('textarea');
        el.innerText = text;
        document.body.appendChild(el);
        el.select();
        el.setSelectionRange(0, 99999) //for mobile devices
        const r = document.execCommand('copy');
        el.remove();
        return r;
    }
}

// /** @param {string} [s] */
// export function htmlEntitiesDecode(s) {
//     if (!s) {
//         return '';
//     }
//     const el = document.createElement('div');
//     return s.replace(/&#?[a-z0-9]{2,31};/gi, (enc) => {
//         el.innerHTML = enc;
//         return el.innerText;
//     });
// }

/** @param {string|number} ean @param {0|1|2} [size=0] @param {boolean} [isBack=false] */
export function imageUrl(ean, size, isBack) {
    // if( location.protocol === 'file:' || '127.0.0.1' === location.host) return 'about:blank';   //for debug without image TODO
    //TODO use srcset=""
    //@ts-expect-error variable defined in global.d.ts
    /** @type {string} */(site_env[`image_ean_${isBack ? '4couv' : 'couv'}_${['small', 'mid', 'big'][size ?? 0]}`])
        .replace(
            /\{ean(?:,(-?\d+))?\}/g,
            (_, width) => {
                const v = String(ean);
                const w = width ? parseInt(width, 10) : 0;
                return w
                    ? w > 0 ? v.slice(0, w) : v.slice(w)
                    : v
            }
        );
}

/** @param {number | undefined | null} prix */
export function formatPrix(prix) {
    return typeof prix === 'number'
        ? `${prix.toFixed(2).replace('.00', '')}\u00A0\u20AC`
        : '';
}
