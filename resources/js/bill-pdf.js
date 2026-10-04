/**
 * A4 PDFs of a print container (#bill-print, later #job-print / #quotation-print) with html2canvas + jsPDF,
 * as the old app did (SPEC §1). The two libraries are loaded from cdnjs the first time a PDF is needed.
 */
const SCRIPTS = {
    html2canvas: 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
    jspdf: 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
};

const A4_WIDTH_MM = 210;
const A4_HEIGHT_MM = 297;

const loaded = {};

function loadScript(src) {
    loaded[src] ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.crossOrigin = 'anonymous';
        script.onload = resolve;
        script.onerror = () => {
            delete loaded[src];
            reject(new Error(`Could not load ${src}`));
        };
        document.head.appendChild(script);
    });

    return loaded[src];
}

/**
 * Render the element to a multi-page A4 jsPDF document.
 */
export async function renderPdf(element) {
    await Promise.all([loadScript(SCRIPTS.html2canvas), loadScript(SCRIPTS.jspdf)]);

    const canvas = await window.html2canvas(element, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
        scrollX: 0,
        scrollY: 0,
        windowWidth: element.scrollWidth,
    });

    const pdf = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
    const image = canvas.toDataURL('image/jpeg', 0.95);
    const imageHeight = (canvas.height * A4_WIDTH_MM) / canvas.width;

    let heightLeft = imageHeight;
    let position = 0;

    pdf.addImage(image, 'JPEG', 0, position, A4_WIDTH_MM, imageHeight);
    heightLeft -= A4_HEIGHT_MM;

    // Anything taller than one page continues on the next, shifted up a page at a time.
    while (heightLeft > 1) {
        position -= A4_HEIGHT_MM;
        pdf.addPage();
        pdf.addImage(image, 'JPEG', 0, position, A4_WIDTH_MM, imageHeight);
        heightLeft -= A4_HEIGHT_MM;
    }

    return pdf;
}

/**
 * Save the element as `{filename}.pdf`.
 */
export async function downloadPdf(element, filename) {
    (await renderPdf(element)).save(`${filename}.pdf`);
}

/**
 * The element as a base64 PDF (no data: prefix), e.g. to attach to an email.
 */
export async function pdfBase64(element) {
    const dataUri = (await renderPdf(element)).output('datauristring');

    return dataUri.slice(dataUri.indexOf(',') + 1);
}
