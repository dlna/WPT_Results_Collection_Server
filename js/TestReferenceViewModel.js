﻿/// <reference path="knockout-3.3.0.debug.js" />
/// <reference path="knockout.mapping-latest.debug.js" />

function TestReferenceViewModel(data)
{
    // Data
    var self = this;
    ko.mapping.fromJS(data, {}, self);
}
