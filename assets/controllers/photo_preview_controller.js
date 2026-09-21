import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    show() {
        const [photo] = this.element.files;
        if (!photo) {
            this.preview?.remove();
            this.preview = null;

            return;
        }

        if (!this.preview) {
            this.preview = document.createElement('img');
            this.preview.className = 'img-thumbnail mt-2';
            this.preview.style.maxHeight = '150px';
            this.element.insertAdjacentElement('afterend', this.preview);
        }

        this.preview.src = URL.createObjectURL(photo);
    }
}