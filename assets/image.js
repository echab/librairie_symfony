// @ts-check

// Upload une image à partir du presse-papier ou d'un fichier

const inputFile = /** @type {HTMLInputElement | null} */(document.getElementById('image_image'));
const imageName = /** @type {HTMLInputElement | null} */(document.getElementById('image_name'));
const buttonPaste = document.getElementById('image_paste');

if (inputFile && imageName && buttonPaste) {
    const PNG = 'image/png';
    buttonPaste.addEventListener('click', async () => {
        const clipboardContents = await navigator.clipboard.read();
        for (const item of clipboardContents) {
            if (item.types.includes(PNG)) {
                const blob = await item.getType(PNG);

                const file = new File(
                    [blob],
                    `${imageName.value.trim() || 'collé'}.png`,
                    {
                        type: PNG,
                        lastModified: new Date().getTime()
                    }
                );
                const container = new DataTransfer();
                container.items.add(file);
                inputFile.files = container.files;

                break;
            }
        }
    })
}


// Insert une image en markdown

const textarea = parent.document.getElementById('post_markdown');
const gallery = document.getElementById('gallery');

if (textarea && gallery) {
    gallery.addEventListener('click', (e) => {
        const elem = /** @type {HTMLElement | null} */(e.target);
        if (elem && elem.tagName === 'IMG') {
            textarea.focus();
            textarea.ownerDocument.execCommand('insertText', false, `\n\n![](${elem.getAttribute('src')})\n\n`);
        }
    });
}
