/// <reference path="jquery-1.9.1.js" />
/// <reference path="bootstrap.js" />
/// <reference path="knockout-3.3.0.debug.js" />
/// <reference path="knockout.mapping-latest.debug.js" />
/// <reference path="sammy.js" />
/// <reference path="modal.js" />

function TestResultsViewModel(data)
{
    // Data
    var self = this;
    ko.mapping.fromJS(data, {}, self);

    // Derived values
    self.resultClasses = ko.pureComputed(function () {
        return self.test.type() + " " + self.result().toLowerCase();
    }, this);
}

function TestSessionViewModel(data)
{
    // Data
    var self = this;
    ko.mapping.fromJS(data, {}, self);

    self.name.subscribe(function (value) {
        $.post(self.href(), JSON.stringify({ name: value }), function () {
        });
    });
}

function TestReferenceViewModel(data)
{
    // Data
    var self = this;
    ko.mapping.fromJS(data, {}, self);
}

function DeleteSessionViewModel(session)
{
    // Data
    var self = this;
    
    self.session = session;
    self.template = "DeleteSession";

    self.complete = function ()
    {
        this.modal.close(true);
    };

    self.cancel = function ()
    {
        // Close the modal without passing any result data.
        this.modal.close();
    };
}

ko.extenders.liveEditor = function (target) {
    target.editing = ko.observable(false);

    target.edit = function () {
        target.editing(true);
    };

    target.stopEditing = function () {
        target.editing(false);
    };
    return target;
};

ko.bindingHandlers.liveEditor = {
    init: function (element, valueAccessor) {
        var observable = valueAccessor();
        observable.extend({ liveEditor: this });
    },
    update: function (element, valueAccessor) {
        var observable = valueAccessor();
        ko.bindingHandlers.css.update(element, function () { return { editing: observable.editing }; });
    }
};

ko.bindingHandlers.executeOnEnter = {
    init: function (element, valueAccessor, allBindings, viewModel) {
        var callback = valueAccessor();
        $(element).keypress(function (event) {
            var keyCode = (event.which ? event.which : event.keyCode);
            if (keyCode === 13) {
                callback.call(viewModel);
                return false;
            }
            return true;
        });
    }
};

ko.bindingHandlers.focus = {
    update: function (element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
        var edit = ko.utils.unwrapObservable(valueAccessor());
        if (edit) element.focus();
    }
};

function HtmlTestToolViewModel()
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

    var referenceListMapping =
    {
        key: function (data) {
            return ko.utils.unwrapObservable(data.id);
        },
        create: function (options) {
            return new TestReferenceViewModel(options.data);
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

    self.referenceList = ko.mapping.fromJS([], referenceListMapping);
    self.referenceListValid = ko.observable(false);

    self.session = ko.observable(false);
    self.results = ko.mapping.fromJS([], resultsMapping);
    self.totals = ko.mapping.fromJS({ PASS:0, FAIL:0, TIMEOUT:0, ERROR:0, ALL:0 }, resultsMapping);
    self.totalResults = ko.observable(0);

    self.validationId = ko.observable(false);

    self.fetching = ko.observable(false);

    self.endpoints = [];

    self.tab = ko.observable(null);

    self.showPass = ko.observable(true);
    self.showFail = ko.observable(true);
    self.showTimeout = ko.observable(true);
    self.showError = ko.observable(true);

    self.pageSize = ko.observable(25);
    self.pageIndex = ko.observable(1);

    self.selectedReference = ko.observable(false);
    self.selectedSession = ko.observable(false);

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

    self.isHome = ko.pureComputed(function () {
        return this.tab() == 'home';
    }, this);
    self.isResults = ko.pureComputed(function () {
        return this.tab() == 'results';
    }, this);
    self.isValidation = ko.pureComputed(function () {
        return this.tab() == 'validation';
    }, this);
    self.isAbout = ko.pureComputed(function () {
        return this.tab() == 'about';
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

    self.sessionListNamed = ko.computed(function () {
        return ko.utils.arrayFilter(self.sessionList(), function (item) {
            return item.name() != "";
        });
    });

    // Behaviours
    self.goToTab = function (tab) { location.hash = tab; };
    self.goToSession = function (session) { location.hash = 'results/' + session.id(); };
    self.goToPage = function (page) {
        self.pageIndex(page);
    };

    self.deleteSession = function (session)
    {
        showModal({
            viewModel: new DeleteSessionViewModel(session),
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
        if(self.endpoints.results)
        {
            self.fetching(true);
            $.get(self.endpoints.results, function (data)
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

    self.updateReferenceList = function (fnCallback)
    {
        if (self.endpoints.references)
        {
            self.fetching(true);
            $.get(self.endpoints.references, function (data)
            {
                ko.mapping.fromJS(data.references, self.referenceList);
                self.referenceListValid(true);
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
        if (self.endpoints.results)
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

    self.session.subscribe(function (id)
    {
        if (false !== id) {
            location.hash = 'results/' + id;
            self.updateResults(id);
        } else {
            self.updateSessionList();
        }
    });

    self.isResults.subscribe(function (selected) 
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

    self.isValidation.subscribe(function (selected) 
    {
        if(selected)
        {
            self.updateSessionList();
            self.updateReferenceList();
        }
        else 
        {

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
        console.log(topic);
        console.log(data);

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

    // Client-side routes    
    var sammy = Sammy(function ()
    {
        this.get('#:tab', function () {
            self.tab(this.params.tab);
            self.session(false);
        });

        this.get('#results/:sessionId', function () {
            self.tab('results');
            self.session(this.params.sessionId);

        });

        this.get('', function () {
            this.redirect('#home');
        });
    });

    // Events from the server
    var conn = new ab.Session('ws://'+window.location.hostname+':8000',
        function ()
        {
            conn.subscribe('html5_test_tool.dlna.org', self.on_server_event);
        },
        function () {
            console.warn('WebSocket connection closed');
        },
        { 'skipSubprotocolCheck': true }
    );

    // Get the endpoints
    $.get("api.php", function (data)
    {
        data.links.forEach(function (item) {
            self.endpoints[item.rel] = item.href;
        });

        // The roughts depend on the endpoints being loaded so run them now
        sammy.run();
    }, 'json');
}

// Activates knockout.js
ko.applyBindings(new HtmlTestToolViewModel());




