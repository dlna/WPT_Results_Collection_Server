/// <reference path="knockout-3.3.0.debug.js" />
/// <reference path="knockout.mapping-latest.debug.js" />

function TestReportViewModel(data)
{
    // Data
    var self = this;
    ko.mapping.fromJS(data, {}, self);
    if (undefined == self.subtest) {
        self.subtest = ko.observable('');
    }

    // Derived values
    self.resultClasses = ko.pureComputed(function () {
        return self.test.type() + " " + self.result().toLowerCase();
    }, this);
}
