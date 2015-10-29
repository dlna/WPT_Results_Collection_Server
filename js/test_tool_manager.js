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
    self.result = ko.pureComputed(function ()
    {
        switch (self.status())
        {
            case "OK":
                var sub = self.subtests();
                for (index in sub)
                {
                    var item = sub[index];
                    if ('PASS' != item.status()) {
                        return item.status();
                    }
                }
                return 'PASS';
            default:
                return self.status();
        }
    }, this);

    self.subResultsCount = function (type)
    {
        var count = 0;
        var sub = self.subtests();
        for (index in sub)
        {
            var item = sub[index];
            if (type == item.status()) {
                count++;
            }
        }
        return count;
    };
    self.subPass = ko.pureComputed(function () {
        return self.subResultsCount('PASS');
    }, this);
    self.subFail = ko.pureComputed(function () {
        return self.subResultsCount('FAIL');
    }, this);
    self.subTimeout = ko.pureComputed(function () {
        return self.subResultsCount('TIMEOUT');
    }, this);
    self.subError = ko.pureComputed(function () {
        return "ERROR" == self.status() ? 1 : 0;
    }, this);
    self.subCount = ko.pureComputed(function () {
        return self.subtests().length;
    }, this);

    self.resultClasses = ko.pureComputed(function () {
        return self.test.type() + " " + self.result().toLowerCase();
    }, this);
}

function TestSessionViewModel(data)
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

function HtmlTestToolViewModel()
{
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
    var self = this;
    self.tab = ko.observable(null);
    self.session = ko.observable(false);
    self.sessionList = ko.mapping.fromJS([], sessionListMapping);
    self.sessionListValid = ko.observable(false);
    self.results = ko.mapping.fromJS([], resultsMapping);

    self.showPass = ko.observable(true);
    self.showFail = ko.observable(true);
    self.showTimeout = ko.observable(true);
    self.showError = ko.observable(true);

    self.endpoints = [];

    // Derived data
    self.isHome = ko.pureComputed(function () {
        return this.tab() == 'home';
    }, this);
    self.isResults = ko.pureComputed(function () {
        return this.tab() == 'results';
    }, this);
    self.isAbout = ko.pureComputed(function () {
        return this.tab() == 'about';
    }, this);

    self.subtestsCount = function (fn) {
        var count = 0;
        var sub = self.results();
        for (index in sub) {
            var item = sub[index];
            count += fn(item);
        }
        return count;
    };

    self.totalPass = ko.pureComputed(function () {
        return self.subtestsCount(function (sub) { return sub.subPass(); });
    }, this);
    self.totalFail = ko.pureComputed(function () {
        return self.subtestsCount(function (sub) { return sub.subFail(); });
    }, this);
    self.totalTimeout = ko.pureComputed(function () {
        return self.subtestsCount(function (sub) { return sub.subTimeout(); });
    }, this);
    self.totalError = ko.pureComputed(function () {
        return self.subtestsCount(function (sub) { return sub.subError(); });
    }, this);
    self.totalCount = ko.pureComputed(function () {
        return self.subtestsCount(function (sub) { return sub.subCount(); });
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
    self.filteredResults = ko.computed(function ()
    {
        var filters = self.filters();
        return ko.utils.arrayFilter(self.results(), function (result) {
            return -1 != filters.indexOf(result.result());
        });
    }, this);

    // Behaviours
    self.goToTab = function (tab) { location.hash = tab; };
    self.goToSession = function (session) { location.hash = 'results/' + session.id(); };

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

    self.updateSessionList = function (fnCallback) 
    {
        if(self.endpoints.results)
        {
            $.get(self.endpoints.results, function (data)
            {
                ko.mapping.fromJS(data.sessions, self.sessionList);
                self.sessionListValid(true);
                if (fnCallback) {
                    fnCallback();
                }
            }, 'json');
        }
    };

    self.updateResults = function (id)
    {
        if (self.endpoints.results)
        {
            if (self.sessionListValid())
            {
                $.get(self.getEndpointForSession(id), function (data) {
                    ko.mapping.fromJS(data.results, self.results);
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

    self.tab.subscribe(function (newTab) 
    {
        switch(newTab)
        {
            case 'results':
                self.updateSessionList();
                break;
        }
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
                    self.results.push(new TestResultsViewModel(data.result));
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




