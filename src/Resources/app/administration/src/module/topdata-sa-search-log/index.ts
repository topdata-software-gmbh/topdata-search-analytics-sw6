import './page/search-log-list';

Shopware.Module.register('topdata-sa-search-log', {
    type: 'plugin',
    name: 'SearchLog',
    title: 'TopdataSearchAnalyticsSW6.topdata-sa-search-log.title',
    description: 'TopdataSearchAnalyticsSW6.topdata-sa-search-log.description',
    color: '#189eff',
    icon: 'default-shopping-search',

    routes: {
        list: {
            component: 'topdata-sa-search-log-list',
            path: 'list',
            meta: {
                privilege: 'system.zero_search.viewer',
            },
        },
    },

    navigation: [{
        id: 'topdata-search-analytics-sw6',
        label: 'TopdataSearchAnalyticsSW6.nav.mainTitle',
        color: '#189eff',
        icon: 'default-shopping-search',
        position: 100,
        parent: 'sw-content',
    }, {
        id: 'topdata-sa-search-log-list',
        label: 'TopdataSearchAnalyticsSW6.nav.searchLog',
        color: '#189eff',
        path: 'topdata.sa.search.log.list',
        parent: 'topdata-search-analytics-sw6',
    }],
});
