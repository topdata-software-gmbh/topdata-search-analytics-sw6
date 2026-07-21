import './page/search-stats-list';

Shopware.Module.register('topdata-sa-search-stats', {
    type: 'plugin',
    name: 'SearchStats',
    title: 'TopdataSearchAnalyticsSW6.topdata-sa-search-stats.title',
    description: 'TopdataSearchAnalyticsSW6.topdata-sa-search-stats.description',
    color: '#189eff',
    icon: 'default-shopping-search',

    routes: {
        list: {
            component: 'topdata-sa-search-stats-list',
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
        id: 'topdata-sa-search-stats-list',
        label: 'TopdataSearchAnalyticsSW6.nav.searchStats',
        color: '#189eff',
        path: 'topdata.sa.search.stats.list',
        parent: 'topdata-search-analytics-sw6',
    }],
});
