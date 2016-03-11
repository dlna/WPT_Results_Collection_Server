/// <reference path="knockout-3.3.0.debug.js" />

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
