<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['data']))
        file_put_contents('settings.json', $_POST['data']);

    die();
}
else {
    $content = '';

    if (file_exists('settings.json'))
        $content = file_get_contents('settings.json');
}

?>

<!doctype html>
<html>
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<title>Cherry - Settings Editor</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="author" content="Thomas Hourdel">

		<link rel="icon" href="favicon.png">

        <style type="text/css" media="screen">
            #ui {
                position: absolute;
                top: 0; right: 0; bottom: 0; left: 0;
                width: 100%;
                background-color: #272727;
            }

            #ui a:link, #ui a:visited, #ui a:active {
                font: 14px/normal 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', 'source-code-pro', monospace;
                color: #ccc;
                display: block;
                width: 100%;
                margin: 0;
                padding: 5px 0;
                text-align: center;
                text-decoration: none;
                font-weight: bold;
            }

            #ui a:hover {
                color: #fff;
            }

            #editor {
                position: absolute;
                top: 26px; right: 0; bottom: 0; left: 0;
            }

            .flash-success {
              animation: flash-success 1s ease-out;
              animation-iteration-count: 1;
            }

            .flash-failure {
              animation: flash-failure 1s ease-out;
              animation-iteration-count: 1;
            }

            @keyframes flash-success { 0% { background-color:none; } 50% { background-color: #6aad28; } 100% {background-color:none; } }
            @keyframes flash-failure { 0% { background-color:none; } 50% { background-color: #d02828; } 100% {background-color:none; } }
        </style>
	</head>

	<body>
        <div id="ui"><a id="save-button" href="#">SAVE</a></div>
        <div id="editor"><?php echo($content); ?></div>

        <script src="js/pack.js"></script>
        <script src="js/ace.js"></script>

        <script>
            $(document).ready(function() {
                var editor = ace.edit("editor");
                editor.setTheme("ace/theme/tomorrow_night_eighties");
                editor.getSession().setMode("ace/mode/json");
                editor.getSession().setTabSize(4);
                editor.getSession().setUseSoftTabs(true);
                editor.getSession().setUseWrapMode(false);
                editor.setBehavioursEnabled(true);
                editor.setHighlightActiveLine(true);
                editor.setHighlightSelectedWord(true);
                editor.setShowFoldWidgets(true);
                editor.setShowInvisibles(false);
                editor.setShowPrintMargin(false);
                editor.setFontSize(14);

                function save(content) {
                    $.post('settings.php', { data: content })
                        .fail(function(e) {
                            console.log(e);
                            $('#ui').addClass('flash-failure');
                            setTimeout(function() { $('#ui').removeClass('flash-failure'); }, 1000);
                        })
                        .done(function() {
                            $('#ui').addClass('flash-success');
                            setTimeout(function() { $('#ui').removeClass('flash-success'); }, 1000);
                        });
                }

                $('a#save-button').on('click', function(e) {
                    e.preventDefault();
                    save(editor.getValue());
                });

                $(document).keydown(function(e) {
                    if(e.ctrlKey && e.which === 83) {
                        e.preventDefault();
                        save(editor.getValue());
                        return false;
                    }
                });
            });
        </script>
	</body>
</html>
