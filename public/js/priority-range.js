$(document).ready(function() {

    $('input[type=range]').on("change", function() {
        let theme = $(this).data('theme');
        let url = $(this).data('url') || "";
        let token = $(this).data('token') || "";
        $.ajax({
            type: "POST",
            url: url,
            data: {
                "priority": $(this).val(),
                'theme': theme,
                "_token": token,
            },
            success: function(responseText){
                let percent = 100 - responseText['priority']
                let element = document.getElementById('priority_'+theme)
                element.innerHTML = '<div class="progress">'+
                    '<div class="progress-bar amount" role="progressbar" id="progress_'+theme+'" style="width: '+percent+'%;" ></div>'+
                '</div>'
                document.getElementById(theme).dataset.priority = responseText['priority']
                if(typeof sortTable === 'function') {
                    sortTable(responseText['day']+"_themes")
                }
                if(document.getElementById(theme).scrollTo) {
                    document.getElementById(theme).scrollTo()
                }
            }
        });
    });
});

