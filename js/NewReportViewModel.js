/// <reference path="knockout-3.3.0.debug.js" />
/// <reference path="knockout.mapping-latest.debug.js" />

function NewReportViewModel(resultsViewModel, validationViewModel)
{
    // Data
    var self = this;

    self.template = 'NewReport';

    self.selectedReference = ko.observable(false);
    self.selectedSession = ko.observable(false);
    
    self.selectionMade = ko.pureComputed(function () {
        return undefined !== self.selectedReference() &&
               undefined !== self.selectedSession();
    });

    self.referenceList = ko.pureComputed(function () { return validationViewModel.referenceList() });
    self.referenceListValid = ko.pureComputed(function () { return validationViewModel.referenceListValid() });

    self.sessionList = ko.pureComputed(function () { return resultsViewModel.sessionList() });
    self.sessionListValid = ko.pureComputed(function () { return resultsViewModel.sessionListValid() });

    self.sessionListNamed = ko.computed(function () {
        return ko.utils.arrayFilter(resultsViewModel.sessionList(), function (item) {
            return item.name() != "";
        });
    });

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
