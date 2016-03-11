/// <reference path="knockout-3.3.0.debug.js" />
/// <reference path="NewReferenceViewModel.js" />
/// <reference path="NewReportViewModel.js" />

function ValidationViewModel(appViewModel, resultsViewModel)
{
    var self = this;

    var referenceListMapping =
    {
        key: function (data) {
            return ko.utils.unwrapObservable(data.id);
        },
        create: function (options) {
            return new TestReferenceViewModel(options.data);
        }
    };

    var reportListMapping =
    {
        key: function (data) {
            return ko.utils.unwrapObservable(data.id);
        },
        create: function (options) {
            return new TestReportListViewModel(options.data);
        }
    };

    var reportMapping =
    {
        key: function (data) {
            return ko.utils.unwrapObservable(data.id);
        },
        create: function (options) {
            return new TestReportViewModel(options.data);
        }
    };

    var totalsMapping =
    {
    };

    // Data
    self.referenceList = ko.mapping.fromJS([], referenceListMapping);
    self.referenceListValid = ko.observable(false);

    self.validationId = ko.observable(false);

    self.fetching = ko.observable(false);

    self.reportList = ko.mapping.fromJS([], reportListMapping);
    self.reportListValid = ko.observable(false);

    self.report = ko.observable(false);
    self.log = ko.mapping.fromJS([], reportMapping);
    self.totals = ko.mapping.fromJS({
        "tests_not_run": 0,
        "subtests_not_run": 0,
        "tests_failed": 0,
        "subtests_failed": 0,
        "log_total": 0
    }, totalsMapping);

    self.pageSize = ko.observable(25);
    self.pageIndex = ko.observable(1);

    self.resultsViewModel = resultsViewModel;

    // Derived data
    self.totalTestsNotRun = ko.pureComputed(function () { return self.totals.tests_not_run(); });
    self.totalSubtestsNotRun = ko.pureComputed(function () { return self.totals.subtests_not_run(); });
    self.totalTestsFailed = ko.pureComputed(function () { return self.totals.tests_failed(); });
    self.totalSubtestsFailed = ko.pureComputed(function () { return self.totals.subtests_failed(); });
    self.totalEntries = ko.pureComputed(function () { return self.totals.log_total(); });

    self.numPages = ko.pureComputed(function () { return Math.ceil(self.totalEntries() / self.pageSize()); }, this);
    self.pages = ko.pureComputed(function ()
    {
        var pages = [];
        var numPages = self.numPages();

        var startPage = 1;
        var endPage = numPages;

        if (numPages > 7) {
            startPage = self.pageIndex() - 3;
            endPage = self.pageIndex() + 3;
            if (startPage < 1) {
                endPage += 1 - startPage;
                startPage += 1 - startPage;
            }
            else if (endPage > numPages) {
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

    // Behaviours
    self.updateReferenceList = function (fnCallback)
    {
        if (appViewModel.endpoints.references)
        {
            self.fetching(true);
            $.get(appViewModel.endpoints.references, function (data)
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

    self.goToReport = function (report) {
        location.hash = 'validation/' + report.id();
    };
    self.goToPage = function (page) {
        self.pageIndex(page);
    };

    appViewModel.isValidation.subscribe(function (selected)
    {
        if (selected)
        {
            resultsViewModel.updateSessionList();
            self.updateReferenceList();
            self.updateReportList();
        }
        else
        {
            self.report(false);
        }
    });

    self.newReport = function () 
    {
        var newReport = new NewReportViewModel(self.resultsViewModel, self);
        showModal({
            viewModel: newReport,
            context: this // Set context so we don't need to bind the callback function
        }).then(function (result)
        {
            if (appViewModel.endpoints.reports)
            {
                self.fetching(true);
                $.post(appViewModel.endpoints.reports,
                {
                    session: newReport.selectedSession(),
                    reference: newReport.selectedReference()
                }, function (data) {
                    self.fetching(false);
                }, 'json');
            }
        });
    };

    self.deleteReport = function (report)
    {
        showModal({
            viewModel: new DeleteViewModel(report, 'DeleteReport'),
            context: this // Set context so we don't need to bind the callback function
        }).then(function (result) {
            $.ajax({
                url: report.href(),
                type: 'DELETE'
            });
        });
    };

    self.downloadReport = function (report)
    {
        var parser = document.createElement('a');
        parser.href = report.href();
        parser.search = 'download=1';
        location.href = parser.href;
    };

    self.newReference = function ()
    {
        var newReference = new NewReferenceViewModel(self.resultsViewModel, self);
        showModal({
            viewModel: newReference,
            context: this // Set context so we don't need to bind the callback function
        }).then(function (result)
        {
            if (appViewModel.endpoints.references)
            {
                var selectedSessions = newReference.selectedSessions();
                if(selectedSessions.length >= newReference.minSessions())
                {
                    var sessionIds = [];
                    for(var i in selectedSessions) {
                        sessionIds[sessionIds.length] = selectedSessions[i].id();
                    }

                    self.fetching(true);
                    $.post(appViewModel.endpoints.references,
                    {
                        sessions: sessionIds,
                        minPass: newReference.minPasses(),
                        name: newReference.name()
                    }, function (data) {
                        self.fetching(false);
                    }, 'json');
                }
            }
        });
    }
    
    self.deleteReference = function (reference)
    {
        showModal({
            viewModel: new DeleteViewModel(reference, 'DeleteReference'),
            context: this // Set context so we don't need to bind the callback function
        }).then(function (result) {
            $.ajax({
                url: reference.href(),
                type: 'DELETE'
            });
        });
    };

    self.downloadReference = function (reference)
    {
        var parser = document.createElement('a');
        parser.href = reference.href();
        parser.search = 'download=1';
        location.href = parser.href;
    };

    self.updateReportList = function (fnCallback) 
    {
        if(appViewModel.endpoints.reports)
        {
            self.fetching(true);
            $.get(appViewModel.endpoints.reports, function (data)
            {
                ko.mapping.fromJS(data.reports, self.reportList);
                self.reportListValid(true);
                if (fnCallback) {
                    fnCallback();
                }
                self.fetching(false);
            }, 'json');
        }
    };

    self.updateId = false;
    self.delayUpdate = false;
    self.updateReport = function (id)
    {
        if (appViewModel.endpoints.reports)
        {
            if (self.reportListValid())
            {
                if (false !== self.updateId) {
                    self.delayUpdate = true;
                    return;
                }

                self.fetching(true);
                self.updateId = $.get(self.getEndpointForReport(id),
                    /* "filters=" + self.filters() + "&" + */
                    "pageIndex=" + self.pageIndex() + "&" +
                    "pageSize=" + self.pageSize(),
                    function (data)
                    {
                        self.updateId = false;

                        ko.mapping.fromJS(data.report, self.log);
                        ko.mapping.fromJS(data.totals, self.totals);
                        if (self.pageIndex() < 1) {
                            self.pageIndex(1);
                        }
                        if (self.pageIndex() > self.numPages()) {
                            self.pageIndex(self.numPages());
                        }

                        if (self.delayUpdate) {
                            self.delayUpdate = false;
                            self.updateReport(id);
                        }

                        self.fetching(false);
                    }, 'json');
            }
            else
            {
                self.updateReportList(function () {
                    self.updateReport(id);
                })
            }
        }
    };

    self.getEndpointForReport = function (id) {
        for (index in self.reportList()) {
            var item = self.reportList()[index];
            if (id == item.id()) {
                return item.href();
            }
        }
        return null;
    };

    self.report.subscribe(function (id)
    {
        if (false !== id) {
            location.hash = 'validation/' + id;
            self.updateReport(id);
        } else {
            self.updateReportList();
        }
    });

    self.pageIndex.subscribe(function () {
        self.updateReport(self.report());
    });

    self.on_server_event = function (topic, data)
    {
        // This is where you would add the new article to the DOM (beyond the scope of this tutorial)
        switch (data.action)
        {
            case "create":
                if (data.report)
                {
                    if (self.reportListValid()) {
                        self.reportList.push(new TestReportListViewModel(data.report));
                    }
                }
                else if(data.reference)
                {
                    if (self.referenceListValid()) {
                        self.referenceList.push(new TestReferenceViewModel(data.reference));
                    }
                }
                break;

            case "delete":
                if (data.report)
                {
                    if (self.reportListValid())
                    {
                        self.reportList.remove(function (item) {
                            return item.id() == data.report;
                        });
                    }
                }
                else if (data.reference)
                {
                    if (self.referenceListValid()) {
                        self.referenceList.remove(function (item) {
                            return item.id() == data.reference;
                        });
                    }
                }
                break;
        }
    };
}