import tinymce from 'tinymce/tinymce';
import 'tinymce/models/dom/model';
import 'tinymce/icons/default/icons';
import 'tinymce/themes/silver/theme';
import 'tinymce/plugins/autolink';
import 'tinymce/plugins/lists';
import 'tinymce/plugins/link';
import 'tinymce/plugins/code';

window.tinymce = tinymce;

const TINYMCE_BASE = {
    license_key: 'gpl',
    skin_url: '/tinymce/skins/ui/oxide',
    content_css: '/tinymce/skins/content/default/content.css',
    menubar: false,
    branding: false,
    promotion: false,
    plugins: 'autolink lists link code',
    toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link | code',
};

window.initTinyEditor = function (id, get, set, height = 300) {
    const existing = tinymce.get(id);
    if (existing) existing.remove();

    const el = document.getElementById(id);
    if (!el) return;

    tinymce.init({
        ...TINYMCE_BASE,
        selector: '#' + id,
        height,
        setup: (editor) => {
            editor.on('init', () => editor.setContent(get() || ''));
            editor.on('input change undo redo', () => {
                const html = editor.getContent();
                if (html !== get()) set(html);
            });
        },
    });
};

window.tinymceField = (options) => ({
    model: options.model,
    height: options.height || 300,
    init() {
        const start = () => this.$nextTick(() => {
            initTinyEditor(options.id, () => this.model, (v) => { this.model = v; }, this.height);
        });

        start();

        window.addEventListener('tl-editor:activate', (e) => {
            if (e.detail?.id === options.id) start();
        });

        this.$watch('model', (v) => {
            const ed = tinymce.get(options.id);
            if (ed && ed.getContent() !== v) ed.setContent(v || '');
        });
    },
});
