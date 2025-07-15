setTimeout(function() {
    const iframe = document.querySelector('iframe');
    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

    // Create a style element
    const style = iframeDoc.createElement('style');
    style.textContent = `
        @font-face {
            font-family: 'Proxima Nova';
            src: url('/wp-content/themes/goonj-crm/fonts/Proxima Nova Regular.otf') format('opentype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Proxima Nova';
            src: url('/wp-content/themes/goonj-crm/fonts/Proxima Nova Bold.otf') format('opentype');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'Proxima Nova';
            src: url('/wp-content/themes/goonj-crm/fonts/Proxima Nova Regular Italic.otf') format('opentype');
            font-weight: normal;
            font-style: italic;
        }
        @font-face {
            font-family: 'Proxima Nova';
            src: url('/wp-content/themes/goonj-crm/fonts/Proxima Nova Bold Italic.otf') format('opentype');
            font-weight: bold;
            font-style: italic;
        }

        p, span, button, a {
            font-family: 'Proxima Nova', sans-serif !important;
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
