/// <reference path="knockout-3.3.0.debug.js" />
/// <reference path="knockout.mapping-latest.debug.js" />

function ResultsViewModel(appViewModel)
{
    var self = this;

    var sessionListMapping =
    {
        key: function(data) {
            return ko.utils.unwrapObservable(data.id);
        },
        create: function (options) {
            return new TestSessionViewModel(options.data);
        }
    };

    var resultsMapping =
    {
        key: function(data) {
            return ko.utils.unwrapObservable(data.id);
        },
        create: function (options) {
            return new TestResultsViewModel(options.data);
        }
    };

    // Data
    self.sessionList = ko.mapping.fromJS([], sessionListMapping);
    self.sessionListValid = ko.observable(false);

    self.session = ko.observable(false);
    self.results = ko.mapping.fromJS([], resultsMapping);
    self.totals = ko.mapping.fromJS({ PASS:0, FAIL:0, TIMEOUT:0, ERROR:0, ALL:0 }, resultsMapping);
    self.totalResults = ko.observable(0);

    self.fetching = ko.observable(false);

    self.showPass = ko.observable(true);
    self.showFail = ko.observable(true);
    self.showTimeout = ko.observable(true);
    self.showError = ko.observable(true);

    self.pageSize = ko.observable(25);
    self.pageIndex = ko.observable(1);

    // Derived data
    self.numPages = ko.pureComputed(function () { return Math.ceil(self.totalResults() / self.pageSize()); }, this);
    self.pages = ko.pureComputed(function ()
    {
        var pages = [];
        var numPages = self.numPages();

        var startPage = 1;
        var endPage = numPages;

        if (numPages > 7)
        {
            startPage = self.pageIndex() - 3;
            endPage = self.pageIndex() + 3;
            if (startPage < 1)
            {
                endPage += 1 - startPage;
                startPage += 1 - startPage;
            }
            else if(endPage > numPages)
            {
                startPage -= endPage - numPages;
                endPage -= endPage - numPages;
            }
        }

        if (startPage > 1) {
            pages.push('…');
        }
        for (var i = startPage; i <= endPage; i++) {
            pages.push(i);
        }
        if (endPage < numPages) {
            pages.push('…');
        }
        return pages;
    }, this);

    self.totalPass = ko.pureComputed(function () {
        return this.totals.PASS();
    }, this);
    self.totalFail = ko.pureComputed(function ()
    {
        return this.totals.FAIL();
    }, this);
    self.totalTimeout = ko.pureComputed(function ()
    {
        return this.totals.TIMEOUT();
    }, this);
    self.totalError = ko.pureComputed(function ()
    {
        return this.totals.ERROR();
    }, this);
    self.totalCount = ko.pureComputed(function ()
    {
        return this.totals.ALL();
    }, this);

    self.filters = ko.computed(function ()
    {
        var filters = [];
        if (self.showPass()) { filters.push("PASS") }
        if (self.showFail()) { filters.push("FAIL") }
        if (self.showTimeout()) { filters.push("TIMEOUT") }
        if (self.showError()) { filters.push("ERROR") }
        return filters;
    });

    self.deleteSession = function (session)
    {
        showModal({
            viewModel: new DeleteViewModel(session, 'DeleteSession'),
            context: this // Set context so we don't need to bind the callback function
        }).then(function (result) {
            $.ajax({
                url: session.href(),
                type: 'DELETE'
            });
        });
    };

    self.downloadSession = function (session)
    {
        var parser = document.createElement('a');
        parser.href = session.href();
        parser.search = 'download=1';
        location.href = parser.href;
    };

    self.updateSessionList = function (fnCallback) 
    {
        if(appViewModel.endpoints.results)
        {
            self.fetching(true);
            $.get(appViewModel.endpoints.results, function (data)
            {
                ko.mapping.fromJS(data.sessions, self.sessionList);
                self.sessionListValid(true);
                if (fnCallback) {
                    fnCallback();
                }
                self.fetching(false);
            }, 'json');
        }
    };

    self.updateId = false;
    self.delayUpdate = false;
    self.updateResults = function (id)
    {
        if (appViewModel.endpoints.results)
        {
            if (self.sessionListValid())
            {
                if (false !== self.updateId) {
                    self.delayUpdate = true;
                    return;
                }

                self.fetching(true);
                self.updateId = $.get(self.getEndpointForSession(id),
                    "filters=" + self.filters() + "&" +
                    "pageIndex=" + self.pageIndex() + "&" +
                    "pageSize=" + self.pageSize(),
                    function (data)
                    {
                        self.updateId = false;

                        ko.mapping.fromJS(data.results, self.results);
                        ko.mapping.fromJS(data.totals, self.totals);
                        self.totalResults(data.numResults);
                        if (self.pageIndex() < 1) {
                            self.pageIndex(1);
                        }
                        if(self.pageIndex() > self.numPages()) {
                            self.pageIndex(self.numPages());
                        }

                        if (self.delayUpdate)
                        {
                            self.delayUpdate = false;
                            self.updateResults(id);
                        }

                        self.fetching(false);
                    }, 'json');
            }
            else
            {
                self.updateSessionList(function () {
                    self.updateResults(id);
                })
            }
        }
    };

    self.getEndpointForSession = function (id)
    {
        for(index in self.sessionList())
        {
            var item = self.sessionList()[index];
            if (id == item.id()) {
                return item.href();
            }
        }
        return null;
    };

    self.goToSession = function (session) { location.hash = 'results/' + session.id(); };
    self.goToPage = function (page) {
        self.pageIndex(page);
    };

    self.session.subscribe(function (id)
    {
        if (false !== id) {
            location.hash = 'results/' + id;
            self.updateResults(id);
        } else {
            self.updateSessionList();
        }
    });

    appViewModel.isResults.subscribe(function (selected)
    {
        if(selected)
        {
            self.updateSessionList();
        }
        else
        {
            self.session(false);
            ko.mapping.fromJS([], self.results);
            ko.mapping.fromJS({ PASS: 0, FAIL: 0, TIMEOUT: 0, ERROR: 0, ALL: 0 }, self.totals);
        }
    });

    self.filters.subscribe(function () {
        self.updateResults(self.session());
    });

    self.pageIndex.subscribe(function () {
        self.updateResults(self.session());
    });

    self.on_server_event = function (topic, data) 
    {
        // This is where you would add the new article to the DOM (beyond the scope of this tutorial)
        switch(data.action)
        {
            case "create":
                if (self.sessionListValid())
                {
                    self.sessionList.push(new TestSessionViewModel(data.session));
                }
                break;
            case "delete":
                if (self.sessionListValid())
                {
                    self.sessionList.remove(function (item) {
                        return item.id() == data.session;
                    });
                }
                break;
            case "result":
                if (self.sessionListValid())
                {
                    for (index in self.sessionList())
                    {
                        var item = self.sessionList()[index];
                        if (data.session.id == item.id())
                        {
                            item.count(data.session.count);
                            item.modified(data.session.modified);
                        }
                    }
                }

                if (self.session() == data.session.id)
                {
                    self.updateResults(self.session());
                }
                break;
        }
    };
}