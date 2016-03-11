/// <reference path="jquery-1.9.1.js" />
/// <reference path="bootstrap.js" />
/// <reference path="knockout-3.3.0.debug.js" />
/// <reference path="knockout.mapping-latest.debug.js" />
/// <reference path="sammy.js" />
/// <reference path="modal.js" />

function HtmlTestToolViewModel()
{
    var self = this;

    // Data
    self.endpoints = [];

    self.tab = ko.observable(null);

    self.config = ko.mapping.fromJS({admin: false});

    // Derived data
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

    // Behaviours
    self.goToTab = function (tab) { location.hash = tab; };

    self.on_server_event = function (topic, data) 
    {
        // This is where you would add the new article to the DOM (beyond the scope of this tutorial)
        console.log(topic);
        console.log(data);

        if (data.session) {
            self.resultsViewModel().on_server_event(topic, data);
        }
        if (data.report || data.reference) {
            self.validationViewModel().on_server_event(topic, data);
        }
    };

    // Tab View models
    self.resultsViewModel = ko.observable(new ResultsViewModel(self));
    self.validationViewModel = ko.observable(new ValidationViewModel(self, self.resultsViewModel()));

    // Client-side routes    
    var sammy = Sammy(function ()
    {
        this.get('#:tab', function () {
            self.tab(this.params.tab);
            self.resultsViewModel().session(false);
            self.validationViewModel().report(false);
        });

        this.get('#results/:sessionId', function () {
            self.tab('results');
            self.resultsViewModel().session(this.params.sessionId);
        });

        this.get('#validation/:reportId', function () {
            self.tab('validation');
            self.validationViewModel().report(this.params.reportId);
        });

        this.get('', function () {
            this.redirect('#home');
        });
    });

    // Events from the server
    var conn = new ab.Session('ws://'+window.location.hostname+':9001',
        function ()
        {
            conn.subscribe('html5_test_tool.dlna.org', self.on_server_event);
        },
        function () {
            console.warn('WebSocket connection closed');
        },
        { 'skipSubprotocolCheck': true }
    );

    // Get the config
    $.get("config.json", function (data)
    {
        ko.mapping.fromJS(data, self.config);
    }, 'json');

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
var htmlTestTool = new HtmlTestToolViewModel();
ko.applyBindings(htmlTestTool);
