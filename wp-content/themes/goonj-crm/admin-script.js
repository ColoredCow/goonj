setTimeout(function() {
    const iframe = document.querySelector('iframe');
    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

    // Create a style element
    const style = iframeDoc.createElement('style');
    style.textContent = `
        @font-face {
            font-family: 'Proxima Nova';
            src: url('/wp-content/themes/goonj-crm/fonts/Proxima%20Nova%20Regular.otf') format('otf');
            font-weight: normal;
            font-style: normal;
        }
    `;
    iframeDoc.head.appendChild(style);

    const fontFamily = 'Proxima Nova'

    const paragraphs = iframeDoc.querySelectorAll('p');
    paragraphs.forEach(function(p) {
        p.style.setProperty('font-family', fontFamily, 'important');
    });

    const spans = iframeDoc.querySelectorAll('span');
    spans.forEach(function(span) {
        span.style.setProperty('font-family', fontFamily, 'important');
    });

    const buttons = iframeDoc.querySelectorAll('button');
    buttons.forEach(function(button) {
        button.style.setProperty('font-family', fontFamily, 'important');
    });

    const anchors = iframeDoc.querySelectorAll('a');
    anchors.forEach(function(anchor) {
        anchor.style.setProperty('font-family', fontFamily, 'important');
    });
}, 1500);
