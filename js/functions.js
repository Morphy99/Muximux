function checkScrolling(tabs) {
    var totalTabWidth = parseInt(tabs.children('.cd-tabs-navigation').width()),
        tabsViewport = parseInt(tabs.width());
    if (tabs.scrollLeft() >= totalTabWidth - tabsViewport) {
        tabs.parent('.cd-tabs').addClass('is-ended');
    } else {
        tabs.parent('.cd-tabs').removeClass('is-ended');
    }
}

// Measure viewport and subtract the height the navigation tabs, then resize the iframes.
function resizeIframe() {
    var newSize = $(window).height() - $('nav').height();
    $('iframe').css({'height': newSize + 'px'});
}

function dropDownFixPosition(button, dropdown) {
    var dropDownTop = button.offset().top + button.outerHeight();
    dropdown.css('top', dropDownTop + "px");
    dropdown.css('left', $(window).width() - $('.drop-nav').width() - button.offset().left + "px");
}

function settingsEventHandlers() {
    $('#sortable').sortable();

    //Anytime a radio box for default is changed it unchecks the others
    $('input[type=radio]').change(function () {
        $('input[type=radio]:checked').not(this).prop('checked', false);
    });

    //Event Handler for show/hide instructions
    $('#showInstructions').click(function () {
        $('#instructionsContainer').slideToggle(1000);
        if ($(this).html() == "<span class=\"fa fa-book\"></span> Show Instructions")
            $(this).html('<span class=\"fa fa-book\"></span> Hide Instructions');
        else
            $(this).html('<span class=\"fa fa-book\"></span> Show Instructions');

    });

    //Event Handler for show/hide changelog
    $('#showChangelog').click(function () {
        $('#changelogContainer').slideToggle(1000);
        viewChangelog();
        if ($(this).html() == "<span class=\"fa fa-github\"></span> Show Updates")
            $(this).html('<span class=\"fa fa-github\"></span> Hide Updates');
        else
            $(this).html('<span class=\"fa fa-github\"></span> Show Updates');
    });

    //Event Handler for backup.ini show/hide button
    if ($('#backupContents').text() != "") {
        $('.btn-group').append('<a class="btn btn-primary" id="showBackup"><span class=\"fa fa-book\"></span> Show Backup INI</a>')
        $('.btn-group').css('width','425px')
        $('#showBackup').click(function () {
            $('#backupiniContainer').slideToggle(1000);
            if ($(this).html() == "<span class=\"fa fa-book\"></span> Show Backup INI")
                $(this).html('<span class=\"fa fa-book\"></span> Hide Backup INI');
            else
                $(this).html('<span class=\"fa fa-book\"></span> Show Backup INI');
        });
    }

    //Remove sortable item button handler
    $('form').on('click', '.removeButton', function () {
        if (confirm('Are you sure?')) {
            var selectedEffect = "drop";
            var options = {};
            $($(this).parents('.applicationContainer')).effect(selectedEffect, options, 500, removeCallback($(this).parents('.applicationContainer')));
        }
    });
    function removeCallback(selectedElement) {
        setTimeout(function () {
            $(selectedElement).remove();
        }, 1000);
    };

    //Fix for iconpicker. For some reason the arrow doesn't get disabled when it hits the minimum/maximum page number. This disables the button, so that it doesnt go into the negatives or pages above its max.
    $('body').on('click', '.btn-arrow', function (event) {
        event.preventDefault();
        if ($(this).hasClass('disabled'))
            $(this).attr('disabled', 'disabled');
        else
            $('.btn-arrow').removeAttr('disabled');

    });

    //Add new application button handler
    $('#addApplication').click(function () {
        //Generating a random number here. So that if the user adds more than one new application at a time the ids/classes and names don't match.
        var rand = Math.floor((Math.random() * 999999) + 1);
        $('#sortable').append(
            '<div class="applicationContainer newApp" id="' + rand + 'newApplication"><span class="bars fa fa-bars"></span>' +
            '<div><label>Name:</label><input class="appName ' + rand + 'newApplication-value" name="' + rand + 'newApplication-name" type="text" value=""></div>' +
            '<div><label>URL:</label><input class="' + rand + 'newApplication-value" name="' + rand + 'newApplication-url" type="text" value=""></div>' +
            '<div><label>Icon:</label><button class=\"' + rand + 'newApplication-value iconpicker btn btn-default\" name="' + rand + 'newApplication-icon"  data-iconset=\"fontawesome\" data-icon=\"\"></button></div>' +
            '<div><label for="' + rand + 'newApplication-enabled">Enable:</label><input class="checkbox ' + rand + 'newApplication-value" id="' + rand + 'newApplication-enabled" name="' + rand + 'newApplication-enabled" type="checkbox" checked></div>' +
            '<div><label for="' + rand + 'newApplication-default">Default:</label><input class="radio ' + rand + 'newApplication-value" id="' + rand + 'newApplication-default" name="' + rand + 'newApplication-default" type="radio"></div>' +
            '<div><label for="' + rand + 'newApplication-landingpage">Enable landing page:</label><input class="checkbox ' + rand + 'newApplication-value" id="' + rand + 'newApplication-landingpage" name="newApplication-landingpage" type="checkbox"></div>' +
            '<div><label for="' + rand + 'newApplication-dd">Put in dropdown:</label><input class="checkbox ' + rand + 'newApplication-value" id="' + rand + 'newApplication-dd" name="newApplication-dd" type="checkbox"></div>' +
            '<button type="button" class="removeButton btn btn-danger btn-xs" value="Remove" id="remove-' + rand + 'newApplication">Remove<meta class="newAppRand" value="' + rand + '"></button><meta class="newAppRand" value="' + rand + '"></div></div>');
        initIconPicker('.' + rand + 'newApplication-value[name=' + rand + 'newApplication-icon]');
    });

    //App Name Change/Addition handler
    $('form').on('focusout', '.appName', function () {
        if ($(this).val() != "") {
            $(this).parents('.applicationContainer').removeClass('newApp');
            var section = $(this).attr('was');
            if (section == undefined) {
                section = $(this).parents('.applicationContainer').children('.newAppRand').attr('value') + "newApplication";
                $(this).parents('applicationContainer').children('.newAppRand').remove();
            }

            var newSection = $(this).val().split(' ').join('_');
            $(this).attr('was', newSection);
            $('.' + section + '-value').each(function () {
                var split = $(this).attr('name').split('-');
                if (split[1] == 'icon')
                    $(this).children('input').prop('name', newSection + "-" + split[1]);
                $(this).removeAttr('name')
                    .prop('name', newSection + "-" + split[1])
                    .addClass(newSection + '-value')
                    .removeClass(section + '-value');
            });
            $(this).parents('div.applicationContainer').attr('id', newSection);
        }
    });

    //On Submit handler
    var options = {
        url: 'muximux.php',
        type: 'post',
        success: showResponse
    };
    $('#settingsSubmit').click(function (event) {
        event.preventDefault();
        $('.newApp').remove(); //Remove any new app that isn't filled out.
        $('.checkbox,.radio').each(function () {
            if (!$(this).prop('checked')) {
                var name = $(this).attr('name');
                $('<input type="hidden" name="' + name + '" value="false">').appendTo($(this));
            }
        });
        $('.appName').removeAttr('disabled');
        $("form").ajaxSubmit(options);
    });
}

//Takes all the data we have to generate our changelog
function viewChangelog() {
    var output = "";
    $.ajax({
        url: "https://api.github.com/repos/mescon/Muximux/commits",
        //force to handle it as text
        dataType: "text",
        success: function (data) {

            var json = $.parseJSON(data);
            var status = "<strong>up to date!</strong>";
            if (dataStore().differenceCommits < 0) {
                status = "<strong>" + dataStore().differenceCommits + " commits ahead!</strong>";
            }
            if (dataStore().differenceCommits > 0) {
                status = "<strong>" + dataStore().differenceCommits + " commits behind!</strong>";
            }
            if (!(dataStore().gitDirectory == "readable") && (dataStore().localVersion == "unknown")) {
                status = "running an <strong>unknown version</strong>.<br/>We can read the <code>.git</directory> to see what version you are using, but we were unable to find the <code>git</code> command.";
            }
            if (dataStore().localVersion == "noexec") {
                status = "not allowing Muximux to run the <code>git</code> command to check what version you're on.<br/>Either you can set <code>safe_mode_exec_dir " + dataStore().cwd + "</code>, <strong>or</strong> you can set <code>safe_mode = off</code> inside your <code>" + dataStore().phpini + "</code> file.";
            }
            if (!(dataStore().gitDirectory == "readable") && (dataStore().localVersion == "noexec")) {
                status += "<br>Also, the <code>" + dataStore().cwd + "/.git/</code> directory is not readable. Please make sure that the directory can be read by your webserver.";
            }

            output = "<p>Your install is currently " + status + "<br/>";
            if (dataStore().differenceCommits > 0) {
                output += "The changes from your version to the latest version can be read <a href=\"" + dataStore().compareURL + "\" target=\"_blank\">here</a>.</p>";
            }

            output += "<p>The latest update to Muximux was uploaded to Github " + dataStore().differenceDays + " days ago.</p>";
            output += "<p>If you wan't to update, please do <code>git pull</code> in your terminal, or <a href='https://github.com/mescon/Muximux/archive/master.zip' target='_blank'>download the latest zip.</a></p><br/><h3>Changelog</h3><ul>";
            for (var i in json) {
                var shortCommitID = json[i].sha.substring(0, 7);
                var shortComments = json[i].commit.message.substring(0, 220).replace(/$/, "") + "...";
                var shortDate = json[i].commit.author.date.substring(0, 10);

                output += "<li><pre>" + shortDate + " <a href=\"" + json[i].html_url + "\">" + shortCommitID + "</a>:  " + shortComments + "</li></pre>";

            }
            output += "</ul>";
            $('#changelog').html(output);
        }
    });
}

//Init iconpickers
function initIconPicker(selectedItem) {
    $(selectedItem).iconpicker({
        align: 'center', // Only in div tag
        arrowClass: 'btn-danger',
        arrowPrevIconClass: 'glyphicon glyphicon-chevron-left',
        arrowNextIconClass: 'glyphicon glyphicon-chevron-right',
        cols: 10,
        footer: true,
        header: true,
        iconset: 'fontawesome',
        labelHeader: '{0} of {1} pages',
        labelFooter: '{0} - {1} of {2} icons',
        placement: 'bottom', // Only in button tag
        rows: 5,
        search: true,
        searchText: 'Search',
        selectedClass: 'btn-success',
        unselectedClass: ''
    });
}

// post-submit callback function
function showResponse(responseText, statusText) {
    if (responseText == 1)
        location.pathname = location.pathname;
    else
        alert("Error!!!-" + responseText);
}

//Gets the difference between the latest commit and the commit you are on
function datediff(latestDate) {
    var rightNow = new Date();
    var currentDate = rightNow.toISOString().substring(0, 10).split('-').join('');
    var test = latestDate.split('-').join('');
    return currentDate - test;
}

// Gets values from PHP, save objects as meta tags in body for later retrieval without doing new AJAX calls.
function getSystemData(urlparam) {
    $.ajax({
        type: "GET",
        dataType: "text",
        url: "muximux.php?get=" + urlparam,
        cache: false,
        async: true,
        success: function (data) {
            $('body').append('<meta id="' + urlparam + '-data">');
            $('#' + urlparam + "-data").data({data: data});
        }
    })
}

//Grabs muximux repo data from github api
function getGitHubData() {
    $.ajax({
        async: true,
        dataType: 'json',
        url: "https://api.github.com/repos/mescon/Muximux/commits",
        type: 'GET',
        success: function (data) {
            $('#gitData').data(data);
        }

    });
}

//Grabs data from ajax calls that were stored on elements for later use
function dataStore() {
    var json = $('#gitData').data();
    var localversion = $("#hash-data").data()['data'];
    var cwd = $("#cwd-data").data()['data'];
    var phpini = $("#phpini-data").data()['data'];
    var gitdir = $("#gitdirectory-data").data()['data'];
    var compareURL = "https://github.com/mescon/Muximux/compare/" + localversion + "..." + json[0].sha;
    var difference = 0;
    for (var i in json) {
        if (json[i].sha == localversion) {
            difference = i;
        }
    }
    var differenceDays = datediff(json[0].commit.author.date.substring(0, 10));

    var upstreamInformation = {
        compareURL: compareURL,
        differenceCommits: difference,
        differenceDays: differenceDays,
        latestVersion: json[0].sha,
        localVersion: localversion,
        gitDirectory: gitdir,
        cwd: cwd,
        phpini: phpini
    };
    return upstreamInformation;
}