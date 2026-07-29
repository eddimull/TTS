/**
 * Utilities for notes fields that may contain either plain text (canonical
 * format — what mobile and the web textareas write) or legacy rich-text HTML
 * authored by the old Quill editors.
 *
 * Plain text renders line breaks via `whitespace-pre-wrap`; legacy HTML must
 * either be rendered with v-html or converted to plain text first. Keep all
 * format sniffing/conversion here so every surface treats notes the same way.
 */

/**
 * Detect legacy rich-text HTML content (backward compatibility for notes
 * saved by the old Quill editors).
 * @param {string|null|undefined} text
 * @returns {boolean}
 */
export function isHtmlContent(text) {
    if (!text) return false;
    return /<\/?[a-z][\s\S]*>/i.test(text);
}

/**
 * Convert rich-text HTML to plain text, preserving line structure:
 * paragraphs and <br> become newlines, list items become bullet/numbered
 * lines, headings become uppercase lines.
 * @param {string} html
 * @returns {string}
 */
export function htmlToPlainText(html) {
    const container = document.createElement('div');
    container.innerHTML = html;
    return convertNode(container).replace(/\n{3,}/g, '\n\n').trim();
}

/**
 * Normalize a notes value for display or editing as plain text. Plain text
 * passes through untouched; legacy HTML is converted.
 * @param {string|null|undefined} text
 * @returns {string}
 */
export function noteToPlainText(text) {
    if (!text) return '';
    return isHtmlContent(text) ? htmlToPlainText(text) : text;
}

function convertNode(element) {
    let text = '';

    for (const node of element.childNodes) {
        if (node.nodeType === Node.TEXT_NODE) {
            text += node.textContent;
        } else if (node.nodeType === Node.ELEMENT_NODE) {
            const tagName = node.tagName.toLowerCase();

            switch (tagName) {
                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                    text += node.textContent.trim().toUpperCase() + '\n';
                    break;
                case 'p':
                case 'div': {
                    // Quill emits one <p> per visual line; an empty line is
                    // <p><br></p>, whose inner conversion is already "\n".
                    const inner = convertNode(node);
                    text += (inner === '\n' ? '' : inner) + '\n';
                    break;
                }
                case 'br':
                    text += '\n';
                    break;
                case 'ul':
                    text += convertList(node, false);
                    break;
                case 'ol':
                    text += convertList(node, true);
                    break;
                case 'li':
                    text += '  • ' + node.textContent.trim() + '\n';
                    break;
                default:
                    text += node.textContent;
            }
        }
    }

    return text;
}

function convertList(listElement, isOrdered) {
    let text = '';
    const items = listElement.querySelectorAll('li');

    items.forEach((item, index) => {
        const prefix = isOrdered ? `${index + 1}. ` : '  • ';
        text += prefix + item.textContent.trim() + '\n';
    });

    return text;
}
