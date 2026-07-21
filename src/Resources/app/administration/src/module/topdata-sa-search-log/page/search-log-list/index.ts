import template from './search-log-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('topdata-sa-search-log-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            items: null,
            isLoading: true,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            limit: 25,
            termFilter: null,
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('tdsa_search_log');
        },

        columns() {
            return [{
                property: 'term',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-log.columnTerm'),
                allowResize: true,
                primary: true,
            }, {
                property: 'resultCount',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-log.columnResultCount'),
                allowResize: true,
                sortable: true,
            }, {
                property: 'sessionToken',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-log.columnSessionToken'),
                allowResize: true,
            }, {
                property: 'createdAt',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-log.columnCreatedAt'),
                allowResize: true,
                sortable: true,
            }];
        },
    },

    mounted() {
        this.getList();
    },

    methods: {
        getList() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            if (this.termFilter) {
                criteria.addFilter(Criteria.contains('term', this.termFilter));
            }

            this.repository.search(criteria).then((result) => {
                this.total = result.total;
                this.items = result;
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onPageChange(params) {
            this.page = params.page;
            this.limit = params.limit;
            this.getList();
        },

        onSortColumn(column) {
            this.sortBy = column.dataIndex ?? column.property;
            this.sortDirection = column.sortDirection ?? 'ASC';
            this.getList();
        },

        onRefresh() {
            this.getList();
        },

        onSearchTerm() {
            this.page = 1;
            this.getList();
        },
    },
});
