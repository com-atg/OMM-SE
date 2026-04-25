export function registerCsvFileDrop(Alpine) {
    Alpine.data('csvFileDrop', (inputId) => ({
        isDragging: false,

        dropFile(event) {
            this.isDragging = false;

            const file = event.dataTransfer?.files?.[0];

            if (!file) {
                return;
            }

            const input = document.getElementById(inputId);

            if (!input) {
                return;
            }

            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },
    }));
}
