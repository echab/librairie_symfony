// @ts-check

import { createMemo, onCleanup, createSignal, createResource, Suspense, For, Show } from "solid-js";
import html from "solid-js/html";
import { formatPrix, highlight, imageUrl } from "./util.js";
import { AddPanier } from "./panier.js";
/** @import { Livre, Code, Coeur, Rayon, Rayons, Order, ElemEvent, EventHandler } from "./types.ts" */

const baseUrl = new URL('../api/', import.meta.url).href;

const NOW = Date.now();
const PAST = new Date('2016-01-01'); //unknown date are supposed old while sorting
const NEWS = 8;  //number of weeks for news
const dtfShort = new Intl.DateTimeFormat('fr', { month: 'short', year: 'numeric' });
const dtfLong = new Intl.DateTimeFormat('fr', { month: 'short', year: 'numeric', day: 'numeric' });
const dtfHour = new Intl.DateTimeFormat('fr', { month: 'short', year: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric' });

/** @type {Record<Order, (a:Livre, b:Livre) => number> } */
const livreComparator = /** @type {const} */ {
    t(a, b) { return String(a.t).localeCompare(b.t); },
    a(a, b) { return (b.a ? String(a.a).localeCompare(b.a) : 0) || livreComparator.d(a, b); },
    d(a, b) { return (b.d || PAST).getTime() - (a.d || PAST).getTime(); },
    p(a, b) { return (b.p - a.p) || livreComparator.d(a, b); },
};

const [coeurs] = createResource(fetchCoeurs);
const [rayons] = createResource(fetchRayons);

export function Stock() {
    const [stock] = createResource(fetchStock);

    const ageStock = () => (Date.now() - (stock()?.exportDate?.getTime() ?? 0)) / 3600000;

    const [filter, setFilter] = createSignal(parseHash(location.hash));
    /** @param {Partial<ReturnType<typeof parseHash>>} items */
    function getHash(items) {
        const { rayon, order, mot } = filter();
        return `#${items.rayon ?? rayon}-${items.order ?? order}-${trim(items.mot ?? mot)}`;
    }
    const onHashChange = (/** @type {HashChangeEvent} */ e) => {
        if (e.newURL !== e.oldURL) { setFilter(parseHash(e.newURL)); }
    };
    addEventListener("hashchange", onHashChange);
    onCleanup(() => removeEventListener("hashchange", onHashChange));

    const search = createMemo(() => {
        const { mot, rayon, order } = filter();
        return searchLivres(stock() ?? [], mot, rayon, order);
    });

    /** @type {EventHandler<HTMLFormElement, Event>} */
    function changeRayon(e) {
        e && setFilter((f) => ({ ...f, rayon: e.currentTarget.value }));
    };

    /** @type {EventHandler<HTMLFormElement, SubmitEvent>} */
    const doSearch = (e) => {
        e.preventDefault();
        const form = e.currentTarget;
        location.hash = getHash({ mot: form.mot.value, rayon: form.rayon.value });
    }

    return html`
        <form method="get" role="search" class="inline" onSubmit=${doSearch}>
            <p>
                <label>
                Chercher: <input type="search" name="mot" placeholder="un titre, un auteur, ..." value=${() => filter().mot} />
                </label>
                <button type="submit">
                    <i class="genericon genericon-search"></i>
                </button>
            </p>
            <p>
                <${Suspense}>
                    <label>dans le rayon: <${Rayons} rayon=${() => filter().rayon} onChange=${changeRayon} count=${() => search().countSearchByCategory} /></label>
                <//>
            </p>
            <tab class="order">
                trié par :
                <a href=${() => getHash({ order: 't' })} aria-selected=${() => filter().order === 't'}>Titre</a>
                <a href=${() => getHash({ order: 'a' })} aria-selected=${() => filter().order === 'a'}>Auteur</a>
                <a href=${() => getHash({ order: 'd' })} aria-selected=${() => filter().order === 'd'}>Date de sortie</a>
                <a href=${() => getHash({ order: 'p' })} aria-selected=${() => filter().order === 'p'}>Prix</a>
            </tab>
        </form>
            <${Suspense} fallback=${html`<div>Chargement...</div>`}>
                <${Show} when=${ageStock() > 24}>${() => html`
                    <p class="warning">Date du stock : ${() => dtfHour.format(stock()?.exportDate) + `, il y a ${Math.round(ageStock() / 24)} jours.`}</p>
                `}

                <${For} each=${() => search().found}>${/** @param {Livre} livre */(livre) => html`
                    <${Livre} livre=${() => livre} reHighlight=${() => search().reHighlight} />
                `}
                <//>
                <${Show} when=${() => search().foundOther.length}>${(/** @type {Event} */ _e) => html`
                    <i>Trouvé dans les autres rayons:</i>
                    <${For} each=${() => search().foundOther}>${/** @param {Livre} livre */(livre) => html`
                        <${Livre} livre=${() => livre} reHighlight=${() => search().reHighlight} />
                    `}
                    <//>
                `}
                <//>
                <p>Date du stock : ${() => dtfHour.format(stock()?.exportDate)}</p>
            <//>
        </p>
    `;
};

/** @param {{ rayon: Code, onChange: EventHandler<HTMLSelectElement, Event>, count: Record<Code, number> }} props */
function Rayons(props) {
    // <select name='rayon' onChange=${(/** @type {EventT<HTMLSelectElement>} */e) => props.changeRayon(e)}}>
    return html`
    <select name='rayon' onChange=${props.onChange}>
        <option value='all'>tous (${() => props.count.all})</option>
        ${html`
        <${For} each=${() => rayons()}>${/** @param {Rayon} rayon */(rayon) => html`
            <optgroup label=${() => `${rayon.label} ${props.count[rayon.code] ? `(${props.count[rayon.code]})` : ''}`}>
            <${For} each=${() => Object.values(rayon.sousRayons ?? {})}>${/** @param {Rayon} rayon2 */(rayon2) => html`
                <option value=${() => rayon2.code} selected=${() => props.rayon == rayon2.code}>
                    ${() => `${rayon2.label} ${props.count[rayon2.code] ? `(${props.count[rayon2.code]})` : ''}`}
                </option>
                `}
            <//>
            </optgroup>
            `}
        <//>
        `}
    </select>
    `;
}

/** @param {{ livre: Livre, reHighlight?: RegExp }} props */
function Livre(props) {
    const rayon = () => rayons()?.byCode.get(props.livre.r);
    const coeur = () => {
        const c = coeurs?.()?.byEan.get(props.livre.i);
        if (c) {
            const r = rayon();
            return { ...c, url: `/coups-de-coeur/${(r?.parent ??r)?.slug}/${c?.slug}` };
        }
    }
    return html`
    <article class="livre" aria-selected="false" data-ean=${() => props.livre.i}>
        <img alt=' ' title='Cliquez pour zoomer' class='zoom couv'
            src=${() => imageUrl(props.livre.i)}
            data-src-small=${() => imageUrl(props.livre.i)}
            data-src-big=${() => imageUrl(props.livre.i, 2)}
            loading="lazy">
        <img alt=' ' title='Cliquez pour zoomer' class='zoom ifselected'
            src=${() => imageUrl(props.livre.i, 0, true)}
            data-src-small=${() => imageUrl(props.livre.i, 0, true)}
            data-src-big=${() => imageUrl(props.livre.i, 2, true)}
            loading="lazy"
            onError=${hideMe}>
        <div class="desc">
            <h2>${() => coeur() && html`
                <a title="Coup de cœur des libraires"
                    href=${() => coeur()?.url}
                >❤️&nbsp;</a>
                `}
                ${() => (props.livre.w !== undefined && props.livre.w >= 0) ? html`
                    <span class="new" title=${() => `Paru il y a ${props.livre.w} semaines`}></span>
                ` : ''}
                ${() => highlight(props.livre.t || '(sans titre)', props.reHighlight)}
            </h2>
            ${props.livre.a && html`
            <h3>
                ${() => false && html`
                    <a href=${() => `#-${props.livre.a}`}>${() => highlight(props.livre.a ?? '', props.reHighlight)}</a>
                    ` // TODO search by auteur
            }
                <span>${() => highlight(props.livre.a ?? '', props.reHighlight)}</span>
            </h3>
            `}
            <div class="detail">
                ${false && html`<p>${() => (props.livre.resume || '').replace(/\n/g, '<br/>')}</p>`}
                ${() => coeur()?.t && html`<p>❤️ ${coeur()?.t}</p>`}
                <p class="info">
                    ${props.livre.e && html`<span class="editeur nowrap">${() => 'éditeur: ' + props.livre.e}</span>`}
                    ${props.livre.c && html`<span class="collec nowrap">${() => 'collection: ' + props.livre.c}</span>`}
                    ${props.livre.r && html`
                        <a class="rayon nowrap" href=${() => `#${props.livre.r}`}>rayon: ${() => rayon()?.label ?? ''}</a>
                    `}
                    ${props.livre.d && html`<span class="date nowrap" title=${() => dtfLong.format(props.livre.d)}>${() => 'parution: ' + dtfShort.format(props.livre.d)}</span>`}
                    ${false && props.livre.i && html`<span class="ean nowrap">${() => 'code: ' + props.livre.i}</span>`}
                    ${props.livre.p && html`<span class="prix nowrap" title="Prix TTC">${() => `prix: ${formatPrix(props.livre.p)}`}</span>`}
                </p>
                ${props.livre.i && html`
                    <${AddPanier} livre=${() => props.livre} />
                `}
            </div>
        </div>
    </article>
    `;
}

// #region Fetch
async function fetchStock() {
    const resp = await fetch(`${baseUrl}livres`);

    /** @type {Livre[] & { exportDate: Date }} */
    const livres = !resp.ok ? [] : await resp.json();
    livres.exportDate = new Date((livres.length ? livres.splice(livres.length - 1, 1)[0]?.d : null) ?? NOW);

    const WEEK = 7 * 24 * 3600000;
    const reDate = /^(19\d\d|20\d\d)(\d\d)(\d\d)$/;
    const RECENT = NOW - NEWS * WEEK;	//8 weeks ago

    for (const livre of livres) {
        //parse dates
        const a = String(livre.d).match(reDate);
        if (a) {
            livre.d = new Date(a[1] + '-' + a[2] + '-' + a[3]);
            if (livre.d.getTime() > NOW) {
                delete livre.d;
            } else if (livre.d.getTime() > RECENT) {
                livre.w = Math.round((NOW - livre.d.getTime()) / WEEK);
            }
        }

        //cleanup title **, and author XXX
        if (/\*+$/.test(livre.t)) { livre.t = livre.t.replace(/\s*\*+$/, ''); }
        if (/^X+$/i.test(livre.a ?? '')) { livre.a = ''; }
    }
    return livres;
}

async function fetchRayons() {
    const resp = await fetch(`${baseUrl}rayons`);
    /** @type {Rayons} */
    const data = !resp.ok ? [] : await resp.json();

    const rayons = /** @type {Rayon[] & { byCode: Map<Code,Rayon>, bySlug: Map<string,Rayon> }} */(Object.values(data));
    rayons.byCode = new Map();
    rayons.bySlug = new Map();

    for (const r of Object.values(rayons)) {
        rayons.byCode.set(r.code, r);
        rayons.bySlug.set(r.slug, r);
        r.rayons = Object.values(r.sousRayons ?? []);
        for (const r2 of r.rayons) {
            r2.parent = r;
            rayons.byCode.set(r2.code, r2);
            rayons.bySlug.set(r2.slug, r2);
        }
    }
    return rayons;
}

async function fetchCoeurs() {
    const resp = await fetch(`${baseUrl}coeur?ago=12`);
    /** @type {Coeur[]} */
    const data = !resp.ok ? [] : await resp.json();

    const coeurs = /** @type {Coeur[] & { byEan: Map<Code,Coeur>, bySlug: Map<string,Coeur> }} */(Object.values(data));
    coeurs.byEan = new Map();
    coeurs.bySlug = new Map();

    for (const c of coeurs) {
        coeurs.byEan.set(c.i, c);
        coeurs.bySlug.set(c.slug, c);
    }
    return coeurs;
}
// #endregion

// #region Utils

/** @param {string} url */
function parseHash(url) {
    return {
        rayon: /** @type {Code} */ (url.match(/#(\d\d\d|all)\b/)?.[1] ?? 'all'),
        order: /** @type {Order} */ (url.match(/#.*-([tadp])\b/)?.[1] ?? 'd'),
        // ean: parseInt(url.match(/#.*-(\d{13})\b/)?.[1] ?? '-1', 10),
        mot: trim(decodeURIComponent(url).match(/#.*-([a-z0-9àéèêëîïôöùûüç]{2}.+)/i)?.[1] ?? ''),
    };
}

/** @param {string} mot @param {Livre[]} livres @param {Code} rayon @param {Order} order*/
function searchLivres(livres, mot, rayon, order) {
    /** @type {RegExp?} */ let reHighlight = null;
    /** @type {Livre[]} */ const found = [];
    /** @type {Livre[]} */ const foundOther = [];
    /** @type {Record<Code, number>} */ const countSearchByCategory = { all: 0 };

    //keep only words (at least 2 letters)
    let mots = replaceAccents(mot).trim().split(/[^a-z0-9àéèêëîïôöùûüç]+/i)
        .filter((m) => /^[a-z0-9àéèêëîïôöùûüç]{2,20}$/i.test(m));
    if (mots.length > 4) {
        //remove empty words and keep only 4 first
        mots = mots.filter(function (m) { return ! /^(de|la|le|et|du|un|en|au|je|ne|ou|ma|on|ce|tu)$/i.test(m); }).slice(0, 4);
    }
    /** @type {(livre: Livre) => boolean} */
    let hasMots = () => true;
    const withMots = mots.join('').length > 2;
    if (withMots) {
        //TODO remove doublons
        reHighlight = new RegExp('\\b' + mots.join('|\\b'), 'gi');
        const regexps = mots.map(function (m) {
            return new RegExp('\\b' + m, 'i');
            //TODO "exact phrase" between quotes
            //TODO numbers
            //TODO ean
        });
        hasMots = (livre) => {
            const t = replaceAccents(`${livre.t} ${livre.a} ${livre.i}`);
            return regexps.every((re) => re.test(t));
        }
    }

    for (const livre of livres) {
        if (hasMots(livre)) {
            if (!rayon || rayon === 'all' || rayon == livre.r) {
                found.push(livre);
            } else if (withMots) {
                foundOther.push(livre);
            }
            countSearchByCategory.all++;
            countSearchByCategory[livre.r] = (countSearchByCategory[livre.r] || 0) + 1;
        }
    }

    found.sort(livreComparator[order]);
    foundOther.sort(livreComparator[order]);

    return { found, foundOther, reHighlight, countSearchByCategory };
}

/** @param {string} s  */
function replaceAccents(s) {
    return s.replace(/[àéèêëîïôöùûüç]/gi, (m) => 
        ({'à':'a','é':'e','è':'e','ê':'e','ë':'e','î':'i','ï':'i','ô':'o','ö':'o','ù':'o','û':'o','ü':'o','ç':'c'})[m] ?? m
    );
}

/** @param {string} s */
function trim(s) {
    return s.replace(/^\s+|\s+$/g, '');
}

/** @param {ElemEvent} e */
function hideMe(e) {
    e.target?.classList.add('hide');
}
// #endregion

// #region Types
/** @typedef {number} Ean */

/**
 * @typedef {Object} Livre
 * @prop {Ean} i ean
 * @prop {string} t titre
 * @prop {Date | undefined} d parution
 * @prop {number?} w weeks
 * @prop {string?} a auteur
 * @prop {string?} e editeur
 * @prop {string?} c collection
 * @prop {number} p prix
 * @prop {Code} r rayon
 * @prop {number} n
 * @prop {string?} resume
 */

/** @typedef {number | 'all'} Code */
/**
 * @typedef {Object} Rayon
 * @prop {Code} code
 * @prop {string} titre
 * @prop {string} label
 * @prop {string} slug
 * @prop {number} i
 * @prop {Rayon[]} rayons
 * @prop {Rayons} sousRayons
 * @prop {Rayon} parent
 */
/** @typedef {Record<string,Rayon>} Rayons */
/** @typedef {'t' | 'a' | 'd' | 'p'} Order */

/**
 * @typedef {Object} Coeur
 * @prop {string} slug
 * @prop {Ean} i
 * @prop {Date} d
 * @prop {string?} t
 */
// #endregion
