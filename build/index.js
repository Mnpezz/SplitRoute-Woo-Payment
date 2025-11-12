(function() {
    'use strict';
    
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { getSetting } = window.wc.wcSettings;
    const { decodeEntities } = window.wp.htmlEntities;
    const { __ } = window.wp.i18n;
    const { createElement } = window.wp.element;
    
    const settings = getSetting('splitroute_nano_data', {});
    const label = decodeEntities(settings.title) || __('Nano (XNO) via SplitRoute', 'wc-splitroute-nano');
    
    const Icon = function() {
        if (!settings.icon) return null;
        return createElement('img', {
            src: settings.icon,
            style: { float: 'right', height: '24px' }
        });
    };
    
    const Label = function() {
        return createElement('span', 
            { style: { width: '100%' } },
            label,
            createElement(Icon, null)
        );
    };
    
    const Content = function() {
        return decodeEntities(settings.description || '');
    };
    
    registerPaymentMethod({
        name: "splitroute_nano",
        label: createElement(Label, null),
        content: createElement(Content, null),
        edit: createElement(Content, null),
        canMakePayment: function() { return true; },
        ariaLabel: label,
        supports: {
            features: settings.supports || ['products'],
        },
        paymentMethodId: 'splitroute_nano',
        placeOrderButtonLabel: settings.placeOrderButtonLabel || __('Proceed to Nano Payment', 'wc-splitroute-nano'),
    });
})();
