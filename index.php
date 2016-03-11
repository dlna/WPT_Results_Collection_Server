<?PHP

$wpt_base = "http://web-platform.test:8000";
//$wpt_base = "http://localhost:57826/";
$http = array_key_exists("REQUEST_SCHEME", $_SERVER) ? $_SERVER["REQUEST_SCHEME"] : "http";
$server = array_key_exists("HTTP_HOST", $_SERVER) ? $_SERVER["HTTP_HOST"] : "http";
$port = (array_key_exists("SERVER_PORT", $_SERVER) && $_SERVER["SERVER_PORT"] != 80) ? ":".$_SERVER["SERVER_PORT"] : "";
$path = str_replace('//', '/', (array_key_exists("SCRIPT_NAME", $_SERVER) ? str_replace('\\', '/', dirname($_SERVER["SCRIPT_NAME"])) : "")."/api/results");

$results_endpoints = $http."://".$server.$port.$path;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="" />
    <meta name="author" content="" />
    <link rel="icon" href="favicon.ico" />

    <title>DLNA HTML 5 Test Tool</title>

    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet" />

    <!-- Custom styles for this template -->
    <link href="htt.css" rel="stylesheet" />
</head>

<body>

    <nav class="navbar navbar-inverse navbar-fixed-top">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="#">DLNA HTML5 Test Tool</a>
            </div>
            <div id="navbar" class="collapse navbar-collapse">
                <ul class="nav navbar-nav">
                    <li data-bind="css: { active: isHome }">
                        <a href="#home">Home</a>
                    </li>
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">Test Runner</a>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="<?= $wpt_base ?>/tools/runner/tests.html" target="_blank">Simple</a>
                            </li>
                            <li>
                                <a href="<?= $wpt_base ?>/tools/runner/index.html" target="_blank">Full</a>
                            </li>
                        </ul>
                    </li>
                    <li data-bind="css: { active: isResults }">
                        <a href="#results">Results</a>
                    </li>
                    <li data-bind="css: { active: isValidation }">
                        <a href="#validation">Validation</a>
                    </li>
                    <li data-bind="css: { active: isAbout }">
                        <a href="#about">About</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container tab-content">
        <div class="starter-template" data-bind="visible: isHome">
            <h1>DLNA HTML5 Test Tools</h1>
            <div class="alert alert-danger" data-bind="visible:config.admin" role="alert">
                <strong>Warning!</strong> Admin enabled
            </div>
            <p class="lead">
                Welcome to the DLNA HTML 5 Test Tool.
            </p>

            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Results</h3>
                        </div>
                        <div class="panel-body">
                            Panel content
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Reports</h3>
                        </div>
                        <div class="panel-body">
                            Panel content
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div data-bind="visible: isResults, with: resultsViewModel">
            <div data-bind="visible: false === session()">
                <table class="sessions">
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>Name</th>
                            <th>Count</th>
                            <th>Created</th>
                            <th>Modified</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-bind="foreach: sessionList">
                        <tr>
                            <td data-bind="text: id, click: $parent.goToSession"></td>
                            <td data-bind="liveEditor: name, click: name.edit">
                                <span class="view" data-bind="text: name"></span>
                                <input class="edit" data-bind="value: name,
                                                               executeOnEnter: name.stopEditing,
                                                               focus: name.editing,
                                                               event: { blur: name.stopEditing }" />
                            </td>
                            <td data-bind="text: count, click: $parent.goToSession"></td>
                            <td data-bind="text: new Date($data.created() * 1000), click: $parent.goToSession"></td>
                            <td data-bind="text: new Date($data.modified() * 1000), click: $parent.goToSession"></td>
                            <td>
                                <a data-bind="click: $parent.deleteSession" aria-hidden="true" aria-label="Delete">
                                    <span class="glyphicon glyphicon-trash"></span>
                                </a>
                                <a data-bind="click: $parent.downloadSession" aria-hidden="true" aria-label="Download">
                                    <span class="glyphicon glyphicon-download-alt"></span>
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div data-bind="visible: session">
                <p>
                    Session:
                    <span data-bind="text: session"></span>
                </p>

                <table class='table resultsSummary'>
                    <thead>
                        <tr>
                            <th></th>
                            <th>Passed</th>
                            <th>Failed</th>
                            <th>Timeouts</th>
                            <th>Errors</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td></td>
                            <td class="pass" data-bind="text: totalPass"></td>
                            <td class="fail" data-bind="text: totalFail"></td>
                            <td class="timeout" data-bind="text: totalTimeout"></td>
                            <td class="error" data-bind="text: totalError"></td>
                        </tr>
                        <tr>
                            <td>
                                <label>Display:</label>
                            </td>
                            <td>
                                <input type="checkbox" data-bind="checked: showPass" value="PASS" />
                            </td>
                            <td>
                                <input type="checkbox" data-bind="checked: showFail" value="FAIL" />
                            </td>
                            <td>
                                <input type="checkbox" data-bind="checked: showTimeout" value="TIMEOUT" />
                            </td>
                            <td>
                                <input type="checkbox" data-bind="checked: showError" value="ERROR" />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="container" data-bind="visible: fetching">
                    <button class="btn btn-lg btn-warning center-block">
                        <span class="glyphicon glyphicon-refresh glyphicon-refresh-animate"></span>
                        Loading...
                    </button>
                </div>

                <table class="results" data-bind="visible: !fetching()">
                    <thead>
                        <tr>
                            <th>Test</th>
                            <th>Status</th>
                            <th>Message</th>
                            <th>Subtest Pass Rate</th>
                        </tr>
                    </thead>
                    <tbody data-bind="foreach: results">
                        <tr data-bind="css: resultClasses">
                            <td data-bind="text: test.url"></td>
                            <td data-bind="text: result"></td>
                            <td data-bind="text: message"></td>
                            <td>
                                <span data-bind="text: totals.PASS"></span>
                                /
                                <span data-bind="text: totals.ALL"></span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="row pull-right">
                    <ul class="pagination">
                        <li data-bind="css: { disabled: pageIndex() <= 1 }">
                            <a data-bind="click: function () { goToPage(1) }">|&laquo;</a>
                        </li>
                        <li data-bind="css: { disabled: pageIndex() <= 1 }">
                            <a data-bind="click: function () { goToPage(pageIndex() - 1) }">&laquo;</a>
                        </li>
                        <!-- ko foreach: pages -->
                        <li data-bind="css: { active: $data == $parent.pageIndex(), disabled: '&hellip;' == $data }">
                            <a data-bind="text: $data, click: $parent.goToPage.bind($data)"></a>
                        </li>
                        <!-- /ko -->
                        <li data-bind="css: { disabled: pageIndex() >= numPages() }">
                            <a data-bind="click: function () { goToPage(pageIndex() + 1) }">&raquo;</a>
                        </li>
                        <li data-bind="css: { disabled: pageIndex() >= numPages() }">
                            <a data-bind="click: function () { goToPage(numPages()) }">&raquo;|</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div data-bind="visible: isValidation, with: validationViewModel">
            <div class="container" data-bind="visible: fetching">
                <button class="btn btn-lg btn-warning center-block">
                    <span class="glyphicon glyphicon-refresh glyphicon-refresh-animate"></span>
                    Loading...
                </button>
            </div>

            <div data-bind="visible: !fetching()">
                <div data-bind="visible: false === report()">
                    <table class="reportList">
                        <thead>
                            <tr>
                                <th>Id</th>
                                <th>Name</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-bind="foreach: reportList">
                            <tr>
                                <td data-bind="text: id, click: $parent.goToReport"></td>
                                <td data-bind="liveEditor: name, click: name.edit">
                                    <span class="view" data-bind="text: name"></span>
                                    <input class="edit" data-bind="value: name,
                                                               executeOnEnter: name.stopEditing,
                                                               focus: name.editing,
                                                               event: { blur: name.stopEditing }" />
                                </td>
                                <td data-bind="text: new Date($data.created() * 1000), click: $parent.goToReport"></td>
                                <td>
                                    <a data-bind="click: $parent.deleteReport" aria-hidden="true" aria-label="Delete">
                                        <span class="glyphicon glyphicon-trash"></span>
                                    </a>
                                    <a data-bind="click: $parent.downloadReport" aria-hidden="true" aria-label="Download">
                                        <span class="glyphicon glyphicon-download-alt"></span>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div>
                        <button class="btn btn-default" type="button" data-bind="click: newReport">New Report</button>
                    </div>

                    <table class="reportList" data-bind="visible: $root.config.admin">
                        <thead>
                            <tr>
                                <th>Id</th>
                                <th>Name</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-bind="foreach: referenceList">
                            <tr>
                                <td data-bind="text: id"></td>
                                <td data-bind="liveEditor: name, click: name.edit">
                                    <span class="view" data-bind="text: name"></span>
                                    <input class="edit" data-bind="value: name,
                                                               executeOnEnter: name.stopEditing,
                                                               focus: name.editing,
                                                               event: { blur: name.stopEditing }" />
                                </td>
                                <td data-bind="text: new Date($data.created() * 1000)"></td>
                                <td>
                                    <a data-bind="click: $parent.deleteReference" aria-hidden="true" aria-label="Delete">
                                        <span class="glyphicon glyphicon-trash"></span>
                                    </a>
                                    <a data-bind="click: $parent.downloadReference" aria-hidden="true" aria-label="Download">
                                        <span class="glyphicon glyphicon-download-alt"></span>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div>
                        <button class="btn btn-default" type="button" data-bind="click: newReference, visible: $root.config.admin">New Reference</button>
                    </div>
                </div>

                <div data-bind="visible: report">
                    <p>
                        Report:
                        <span data-bind="text: report"></span>
                    </p>

                    <table class='table resultsSummary'>
                        <thead>
                            <tr>
                                <th></th>
                                <th>Tests not run</th>
                                <th>Subtests not run</th>
                                <th>Tests failed</th>
                                <th>Subtests failed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td></td>
                                <td data-bind="text: totalTestsNotRun"></td>
                                <td data-bind="text: totalSubtestsNotRun"></td>
                                <td data-bind="text: totalTestsFailed"></td>
                                <td data-bind="text: totalSubtestsFailed"></td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="reportLog" data-bind="visible: !fetching()">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Test</th>
                                <th>Subtest</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody data-bind="foreach: log">
                            <tr>
                                <td data-bind="text: type"></td>
                                <td data-bind="text: test"></td>
                                <td data-bind="text: subtest"></td>
                                <td data-bind="text: message"></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="row pull-right">
                        <ul class="pagination">
                            <li data-bind="css: { disabled: pageIndex() <= 1 }">
                                <a data-bind="click: function () { goToPage(1) }">|&laquo;</a>
                            </li>
                            <li data-bind="css: { disabled: pageIndex() <= 1 }">
                                <a data-bind="click: function () { goToPage(pageIndex() - 1) }">&laquo;</a>
                            </li>
                            <!-- ko foreach: pages -->
                            <li data-bind="css: { active: $data == $parent.pageIndex(), disabled: '&hellip;' == $data }">
                                <a data-bind="text: $data, click: $parent.goToPage.bind($data)"></a>
                            </li>
                            <!-- /ko -->
                            <li data-bind="css: { disabled: pageIndex() >= numPages() }">
                                <a data-bind="click: function () { goToPage(pageIndex() + 1) }">&raquo;</a>
                            </li>
                            <li data-bind="css: { disabled: pageIndex() >= numPages() }">
                                <a data-bind="click: function () { goToPage(numPages()) }">&raquo;|</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div data-bind="visible: isAbout"></div>
        <!-- /.container -->

        <script type="text/html" id="DeleteSession">
            <div class="modal fade">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Delete session <span data-bind="text: object.id()"></span></h3>
                        </div>
                        <div class="modal-body">
                            Are you sure you wish to delete session <span data-bind="text: object.id()"></span>?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-bind="click: cancel">Cancel</button>
                            <button type="button" class="btn btn-danger" data-bind="click: complete">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="text/html" id="DeleteReport">
            <div class="modal fade">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Delete report <span data-bind="text: object.id()"></span></h3>
                        </div>
                        <div class="modal-body">
                            Are you sure you wish to delete report <span data-bind="text: object.id()"></span>?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-bind="click: cancel">Cancel</button>
                            <button type="button" class="btn btn-danger" data-bind="click: complete">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="text/html" id="DeleteReference">
            <div class="modal fade">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Delete reference <span data-bind="text: object.id()"></span></h3>
                        </div>
                        <div class="modal-body">
                            Are you sure you wish to delete reference <span data-bind="text: object.id()"></span>?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-bind="click: cancel">Cancel</button>
                            <button type="button" class="btn btn-danger" data-bind="click: complete">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="text/html" id="NewReport">
            <div class="modal fade">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Create Report</h3>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="selectedreference">Select reference:</label>
                                <select id="selectedReference" class="form-control"
                                        data-bind="options: referenceList,
                                                           optionsText: 'name',
                                                           optionsValue: 'id',
                                                           value: selectedReference,
                                                           optionsCaption: 'Choose...'"></select>
                            </div>
                            <div class="form-group">
                                <label for="selectedSession">Select results:</label>
                                <select id="selectedSession" class="form-control"
                                        data-bind="options: sessionListNamed,
                                                           optionsText: 'name',
                                                           optionsValue: 'id',
                                                           value: selectedSession,
                                                           optionsCaption: 'Choose...'"></select>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-bind="click: cancel">Cancel</button>
                            <button type="button" class="btn btn-primary" data-bind="click: complete, enable: selectionMade">
                                Validate Results
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="text/html" id="NewReference">
            <div class="modal fade">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Create Reference</h3>
                        </div>
                        <div class="modal-body">
                            <form>
                                <div class="form-group">
                                    <label for="nameInput">Name:</label>
                                    <input type="text" class="form-control" id="nameInput" placeholder="Name" data-bind="value: name">
                                </div>
                            </form>

                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Name</th>
                                        <th>Count</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-bind="foreach: selectedSessions">
                                    <tr>
                                        <td data-bind="text: id"></td>
                                        <td data-bind="text: name"></td>
                                        <td data-bind="text: count"></td>
                                        <td>
                                            <a data-bind="click: $parent.removeSessionFromList" aria-hidden="true" aria-label="Remove">
                                                <span class="glyphicon glyphicon-remove"></span>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <form class="form-inline">
                                <div class="form-group">
                                    <label for="addSession">Select results:</label>
                                    <select id="addSession" class="form-control"
                                            data-bind="options: sessionListNamed,
                                                               optionsText: 'name',
                                                               optionsValue: 'id',
                                                               value: addSession,
                                                               optionsCaption: 'Choose...'"></select>
                                </div>
                                <button type="button" class="btn btn-default" data-bind="click: addSessionToList">Add</button>
                            </form>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-bind="click: cancel">Cancel</button>
                            <button type="button" class="btn btn-primary" data-bind="click: complete, enable: selectionMade">
                                Create reference results
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <!-- JavaScript
             ================================================== -->
        <!-- Placed at the end of the document so the pages load faster -->

        <script src="js/jquery-1.9.1.min.js"></script>
        <script src="js/bootstrap.min.js"></script>
        <script src="js/knockout-3.3.0.js"></script>
        <script src="js/knockout.mapping-latest.js"></script>
        <script src="js/sammy-latest.min.js"></script>
        <script src="js/autobahn.min.js"></script>
        <script src="js/modal.js"></script>

        <script src="js/LiveEditor.js" charset="UTF-8"></script>
        <script src="js/DeleteViewModel.js" charset="UTF-8"></script>
        <script src="js/NewReportViewModel.js" charset="UTF-8"></script>
        <script src="js/NewReferenceViewModel.js" charset="UTF-8"></script>
        <script src="js/TestReferenceViewModel.js" charset="UTF-8"></script>
        <script src="js/TestResultsViewModel.js" charset="UTF-8"></script>
        <script src="js/TestSessionViewModel.js" charset="UTF-8"></script>
        <script src="js/TestReportListViewModel.js" charset="UTF-8"></script>
        <script src="js/TestReportViewModel.js" charset="UTF-8"></script>
        <script src="js/ValidationViewModel.js" charset="UTF-8"></script>
        <script src="js/ResultsViewModel.js" charset="UTF-8"></script>
        <script src="js/test_tool_manager.js" charset="UTF-8"></script>
    </div>
</body>
</html>
