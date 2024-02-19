const createElement = window.wp.element.createElement;
const settings = window.wc.wcSettings.getSetting('craftgate_gateway_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title);

const Content = () => {
    return createElement('Fragment', null,
        createElement('p', null, window.wp.htmlEntities.decodeEntities(settings.description || '')),
        createElement('img', {src: settings.icon, className: 'craftgate-card-brands-icon'})
    )
};
const CraftgateGatewayOptions = {
    name: 'craftgate_gateway',
    label: label,
    content: Object(window.wp.element.createElement)(Content, null),
    edit: Object(window.wp.element.createElement)(Content, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(CraftgateGatewayOptions);