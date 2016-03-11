/// <reference path="knockout-3.3.0.debug.js" />
/// <reference path="knockout.mapping-latest.debug.js" />

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
