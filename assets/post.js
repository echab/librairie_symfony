// @ts-check

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
    /** @param {RegExp} from @param {string} to @param {boolean} [toggle] */
    const editImage = (from, to, toggle) => (/** @type {MouseEvent} */event) => {
        event.preventDefault();
        const sel = getSelectedImage();
        if (sel) {
            txtMarkdown.focus();
            txtMarkdown.setSelectionRange(sel.index, sel.index + sel[0].length);
            txtMarkdown.setRangeText(`![${sel[1]}](${sel[2].replace(from, '') + (toggle && sel[2].includes(to) ? '' : to)})`);
        }
    };
    const btnDef = [
        { innerText: '⬚ à gauche', onclick: editImage(/#droite|#gauche/i, '#gauche', true), to: '#gauche' },
        { innerText: 'à droite ⬚', onclick: editImage(/#droite|#gauche/i, '#droite', true), to: '#droite' },
        { innerText: '⸋ vignette', onclick: editImage(/#vignette/i, '#vignette', true), to: '#vignette' },
    ];
    const buttons = btnDef.map(({ innerText, onclick }) =>
        Object.assign(document.createElement('button'), { innerText, disabled: true, onclick })
    );

    txtMarkdown.onselectionchange = () => {
        const sel = getSelectedImage();
        buttons.forEach((btn, i) => {
            btn.disabled = !sel;
            btn.classList.toggle('primary', sel ? sel[2].includes(btnDef[i].to) : false);
        });
    };

    const divButtons = Object.assign(document.createElement('div'), {
        style: 'display:flex; gap:1em; align-items:center;',
        innerText: 'Image: '
    });
    divButtons.append(...buttons);
    txtMarkdown.after(divButtons);
}

// Aperçu du post

const btnPreview = /** @type {HTMLButtonElement | null} */(document.getElementById('post_actions_preview'));
const divPreview = document.getElementById('divPreview');
if (btnPreview && btnPreview.form && divPreview) {
    btnPreview.addEventListener('click', async (e) => {
        e.preventDefault();
        const formData = /** @type {any} */(new FormData(btnPreview.form ?? undefined, btnPreview));
        const resp = await fetch('/edit/preview/post', {
            method: 'POST',
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams(formData).toString(),
        });
        if (resp.ok) {
            divPreview.innerHTML = await resp.text();
        } else {
            divPreview.innerText = `Erreur ${resp.status} ${resp.statusText}`;
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
