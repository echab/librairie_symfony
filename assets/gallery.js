//@ts-check

/**
 * Tranform the markdown list of images into figures.
 * inspired by: https://github.com/leepenney/markdown-gallery/
 * @param {Record<string,string>} config
 */
export function md_gallery({ list_selector, gallery_tag, gallery_class } = {}) {
    /** @type {NodeListOf<HTMLUListElement>} */
    const lists = document.querySelectorAll(`${list_selector || 'ul'}:has(>li img)`);
    lists.forEach((list) => {
        if (list.parentNode && !list.querySelectorAll('li:not(:has(img))').length) {
            const div = document.createElement(gallery_tag || 'div');
            div.setAttribute('class', gallery_class || 'gallery');

            list.querySelectorAll('li img').forEach((img) => {
                const alt = img.getAttribute('alt');
                const figure = document.createElement('figure');
                figure.setAttribute('aria-selected', 'false');
                figure.setAttribute('data-unselect', 'true');
                const p = img.parentElement; // could be <a href><img></a>
                figure.appendChild(p && p.tagName === 'A' ? p : img);
                if (alt) {
                    const caption = document.createElement('figcaption');
                    caption.textContent = alt;
                    figure.appendChild(caption);
                }
                div.appendChild(figure);
            });

            list.parentNode.replaceChild(div, list);
        }
    });
}
