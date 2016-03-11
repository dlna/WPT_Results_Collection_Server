/// <reference path="knockout-3.3.0.debug.js" />
/// <reference path="knockout.mapping-latest.debug.js" />

function NewReferenceViewModel(resultsViewModel, validationViewModel)
{
    // Data
    var self = this;

    self.template = 'NewReference';

    self.minPasses = ko.observable(2);
    self.minSessions = ko.observable(2);
    self.addSession = ko.observable();
    self.selectedSessions = ko.observableArray();
    self.name = ko.observable();

    self.selectionMade = ko.pureComputed(function ()
    {
        return self.selectedSessions().length >= self.minSessions() && 
               self.name() != null &&
               self.name() != "";
    });

    self.referenceList = ko.pureComputed(function () { return validationViewModel.referenceList() });
    self.referenceListValid = ko.pureComputed(function () { return validationViewModel.referenceListValid() });

    self.sessionList = ko.pureComputed(function () { return resultsViewModel.sessionList() });
    self.sessionListValid = ko.pureComputed(function () { return resultsViewModel.sessionListValid() });

    self.getSessionFromId = function (sessionId)
    {
        var sessions = self.sessionList();
        for (var index in sessions)
        {
            var session = sessions[index];
            if (sessionId == session.id()) {
                return session;
            }
        }

        return null;
    }

    self.sessionAlreadySelected = function (sessionId)
    {
        var sessions = self.selectedSessions();
        for (var index in sessions)
        {
            var session = sessions[index];
            if (sessionId == session.id()) {
                return true;
            }
        }

        return false;
    }

    self.sessionListNamed = ko.computed(function () {
        return ko.utils.arrayFilter(resultsViewModel.sessionList(), function (item) {
            return item.name() != "" && !self.sessionAlreadySelected(item.id());
        });
    });

    self.addSessionToList = function () {
        self.selectedSessions.push(self.getSessionFromId(self.addSession()));
        self.addSession(false);
    }

    self.removeSessionFromList = function (session) {
        self.selectedSessions.remove(session);
    }

    self.complete = function () {
        this.modal.close(true);
    };

    self.cancel = function () {
        // Close the modal without passing any result data.
        this.modal.close();
    };
}
