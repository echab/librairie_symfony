// @ts-check

// Convertit les EAN de la douchette

const txtEAN = /** @type {HTMLInputElement | null} */(document.querySelector('input[pattern*="à&é"]'));
if (txtEAN) {
    txtEAN.addEventListener('change', function(e) {
        const ean = getEAN();
        if (ean) {
            this.value = ean;
        } 
    });
}

function getEAN() {
    const s = txtEAN?.value.trim();
    if (!s?.match(/^[0-9&é"'\(\-è_çà]{13}$/)) {
        return null;
    }
    return s.replace(
        /[^\d]/g,
        (m) => `${'à&é"\'(-è_ç'.indexOf(m)}`
    );
}

// Images: position et taille

const txtMarkdown = /** @type {HTMLTextAreaElement | null} */(document.getElementById('post_markdown'));
if (txtMarkdown && txtMarkdown.parentElement) {
    const reImage = /!\[([^\]]*)\]\(([^)\s]+)\)/g;
    const getSelectedImage = () => {
        if (txtMarkdown) {
            const { selectionStart, selectionEnd, value } = txtMarkdown;
            const images = value.matchAll(reImage);
            for (const img of images) {
                if (img.index <= selectionStart && selectionEnd <= img.index + img[0].length + 1) {
                    return img;
                }
            }
        }
    }

    const insertImage = (/** @type {MouseEvent} */event) => {
        event.preventDefault();
        const ean = getEAN();
        if (ean) {
            const sel = getSelectedImage();
            txtMarkdown.focus();
            if (sel) {
                txtMarkdown.setSelectionRange(sel.index, sel.index + sel[0].length);
            }
            txtMarkdown.setRangeText(`![couverture](https://products-images.di-static.com/image/livre/${ean}-200x303-1.jpg#gauche)\n\n`);
        }
    };

    const editImage = (/** @type {MouseEvent} */event) => {
        event.preventDefault();
        const button = /**@type {HTMLButtonElement} */(event.target);
        const { from, to } = button.dataset;
        const sel = getSelectedImage(); // TODO selection de plusieurs images
        if (sel && from && to) {
            txtMarkdown.focus();
            txtMarkdown.setSelectionRange(sel.index, sel.index + sel[0].length);
            txtMarkdown.setRangeText(`![${sel[1]}](${sel[2].replace(new RegExp(from, 'ig'), '') + (sel[2].includes(to) ? '' : to)})`);
        }
    };
    const buttons = [
        { innerText: '+Couverture', onclick: insertImage, title: "Ajoute une image de couverture à partir de l'EAN", disabled: !txtEAN },
        { innerText: '⬚ à gauche', onclick: editImage, from: '#droite|#gauche', to: '#gauche', disabled: true, title: "Aligne à gauche l'image sélectionnée dans le texte" },
        { innerText: 'à droite ⬚', onclick: editImage, from: '#droite|#gauche', to: '#droite', disabled: true, title: "Aligne à droite l'image sélectionnée dans le texte" },
        { innerText: '⸋ vignette', onclick: editImage, from: '#vignette', to: '#vignette', disabled: true, title: "Affiche en vignette l'image sélectionnée dans le texte" },
    ].map(({ from, to, ...attr }) => {
        const btn = Object.assign(document.createElement('button'), { type: 'button', ...attr })
        if (from) {
            btn.dataset.from = from;
            btn.dataset.to = to;
        }
        return btn;
    });

    txtMarkdown.onselectionchange = () => {
        const sel = getSelectedImage();
        buttons.forEach((btn) => {
            if (btn.dataset.to) {
                btn.disabled = !sel;
                btn.classList.toggle('primary', sel ? sel[2].includes(btn.dataset.to) : false);
            } else {
                btn.disabled = !getEAN();
            }
        });
    };

    const divButtons = Object.assign(document.createElement('div'), {
        style: 'display:flex; gap:1em; align-items:center;',
        innerText: 'Image: '
    });
    divButtons.append(...buttons);
    txtMarkdown.after(divButtons);
}

// Recharge l'EAN du post

const btnReload = /** @type {HTMLButtonElement | null} */(document.getElementById('post_livre_reload'));
if (txtEAN && btnReload && btnReload.form) {
    btnReload.addEventListener('click', async (e) => {
        const ean = getEAN();
        if (ean) {
            document.location = `/edit/coups-de-coeur?ean=${encodeURIComponent(ean)}`;
        }
    });
}

// Aperçu du post

const btnPreview = /** @type {HTMLButtonElement | null} */(document.getElementById('post_actions_preview'));
const divPreview = document.getElementById('divPreview');
if (btnPreview && btnPreview.form && divPreview) {
    btnPreview.form.addEventListener('submit', async (e) => {
        if (e.submitter === btnPreview) {
            e.preventDefault();
            divPreview.innerText = '...';
            const formData = new FormData(/** @type {HTMLFormElement} */(e.target), e.submitter);
            const resp = await fetch('/edit/preview/post', {
                method: 'POST',
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams(/** @type {any} */(formData)).toString(),
            });
            if (resp.ok) {
                divPreview.innerHTML = await resp.text();
            } else {
                divPreview.innerText = `Erreur ${resp.status} ${resp.statusText}`;
            }
        }
    });
}

// Confirm la fermeture de la page sans enregistrer

const btnPublish = /** @type {HTMLButtonElement | null} */(document.getElementById('post_actions_submit'));
if (btnPublish && btnPublish.form) {
    const initialData = new FormData(btnPublish.form);
    let published = false;
    btnPublish.addEventListener('click', () => {
        published = true;
    });
    addEventListener('beforeunload', (e) => {
        if (published) {
            published = false; // cleanup in case of history back
            return;
        }
        const editedData = new FormData(btnPublish.form ?? undefined);
        const updatedFields = [];
        for (const [key, value] of initialData.entries()) {
            if (editedData.get(key) !== value) {
                updatedFields.push(key);
            }
        }
        // if (updatedFields.length && !confirm('Article non enregistré, partir sans publier ?')) {
        if (updatedFields.length) {
            e.preventDefault();
        }
    });
}
