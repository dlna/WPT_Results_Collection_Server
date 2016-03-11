/// <reference path="knockout-3.3.0.debug.js" />
/// <reference path="knockout.mapping-latest.debug.js" />

function DeleteViewModel(object, template)
{
    // Data
    var self = this;
    
    self.object = object;
    self.template = template;

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
