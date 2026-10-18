<!--Footer-part-->

<div class="row-fluid">
    <div id="footer" class="span12" style="color:white"> Designed And Developed By: Your Name</div>
</div>

<!--end-Footer-part-->

<script src="js/excanvas.min.js"></script>
<script src="js/jquery.min.js"></script>
<script src="js/jquery.ui.custom.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.flot.min.js"></script>
<script src="js/jquery.flot.resize.min.js"></script>
<script src="js/jquery.peity.min.js"></script>
<script src="js/fullcalendar.min.js"></script>
<script src="js/matrix.js"></script>
<script src="js/matrix.dashboard.js"></script>
<script src="js/jquery.gritter.min.js"></script>
<script src="js/matrix.interface.js"></script>
<script src="js/matrix.chat.js"></script>
<script src="js/jquery.validate.js"></script>
<script src="js/matrix.form_validation.js"></script>
<script src="js/jquery.wizard.js"></script>
<script src="js/jquery.uniform.js"></script>
<script src="js/select2.min.js"></script>
<script src="js/matrix.popover.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/matrix.tables.js"></script>
</body>
</html>
<style type="text/css">
    .form-inline .form-group{display:inline-block;margin-bottom:0;vertical-align:middle}
    .form-inline .form-control{display:inline-block;width:auto;vertical-align:middle}
    .form-inline .form-control-static{display:inline-block}
    .form-inline .input-group{display:inline-table;vertical-align:middle}
    .form-inline .input-group .form-control,.form-inline .input-group .input-group-addon,.form-inline .input-group .input-group-btn{width:auto}
    .form-inline .input-group>.form-control{width:100%}
    .form-inline .control-label{margin-bottom:0;vertical-align:middle}
    .form-inline .checkbox,.form-inline .radio{display:inline-block;margin-top:0;margin-bottom:0;vertical-align:middle}
    .form-inline .checkbox label,.form-inline .radio label{padding-left:0}
    .form-inline .checkbox input[type=checkbox],.form-inline .radio input[type=radio]{position:relative;margin-left:0}
    .form-inline .has-feedback .form-control-feedback{top:0}}
</style>

<link rel="stylesheet" href="http://code.jquery.com/ui/1.9.2/themes/base/jquery-ui.css" />
<script src="http://code.jquery.com/jquery-latest.js"></script>
<script src="datepicker/jquery-ui.js"></script>

<script type="text/javascript">
    $(function() {
        $("#dt").datepicker({
            dateFormat: 'yy-mm-dd',
            defaultDate: "+1w",
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1,
            startDate: '2019-12-05',
            onClose: function( selectedDate ) {
                $("#dt2").datepicker("option","minDate",selectedDate);
            }
        });
        $("#dt2").datepicker({
            dateFormat: 'yy-mm-dd',
            defaultDate: "+1w",
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1

        });
    });

</script>