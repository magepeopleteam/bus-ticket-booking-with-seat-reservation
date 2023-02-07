(function($) {
    'use strict';

    $(document).ready(function($) {


        var enableDates = $( "#all_date_picker_info" ).data( "enabledates" );
        var off_particular_date = $( "#all_date_picker_info" ).data( "off_particular_date" ) || '';
        var weekly_offday = $( "#all_date_picker_info" ).data( "weekly_offday" ) || '';
        var enable_onday = $( "#all_date_picker_info" ).data( "enable_onday" );
        var enable_offday = $( "#all_date_picker_info" ).data( "enable_offday" );
        var date_format = $( "#all_date_picker_info" ).data( "date_format" );

        if(enable_onday || enable_offday){
            if(enable_onday == 'yes'){
                jQuery('#j_date, #r_date').datepicker({
                    dateFormat: date_format,
                    minDate: 0,
                    beforeShowDay: enableAllTheseDays
                });
            }else{
                jQuery("#j_date, #r_date").datepicker({
                    dateFormat: date_format,
                    minDate: 0,
                    beforeShowDay: off_particular
                });
                jQuery("#j_date, #r_date").datepicker({
                    dateFormat: date_format,
                    minDate: 0,
                    beforeShowDay: weekly_offday_d
                });
            }
        }else{
            if(enableDates){
                jQuery('#j_date, #r_date').datepicker({
                    dateFormat: date_format,
                    minDate: 0,
                    beforeShowDay: enableAllTheseDays
                });
            }else{
                jQuery("#j_date, #r_date").datepicker({
                    dateFormat: date_format,
                    minDate: 0,
                    beforeShowDay: off_particular
                });
                jQuery("#j_date, #r_date").datepicker({
                    dateFormat: date_format,
                    minDate: 0,
                    beforeShowDay: weekly_offday_d
                });
            }
        }


        function enableAllTheseDays(date) {
            var sdate = jQuery.datepicker.formatDate('dd-mm-yy', date)
            if (enableDates.length > 0) {
                if (jQuery.inArray(sdate, enableDates) != -1) {
                    return [true];
                }
            }
            return [false];
        }



                function off_particular(date) {
                    var sdate = jQuery.datepicker.formatDate('dd-mm-yy', date)
                    if (off_particular_date.length > 0) {
                        if (jQuery.inArray(sdate, off_particular_date) != -1) {
                            return [false];
                        }
                    }


                    if (weekly_offday.length > 0) {
                        if (weekly_offday.includes(date.getDay())) {
                            return [false];
                        }
                    }
                    return [true];
                }





                function weekly_offday_d(date) {
                    if (weekly_offday.length > 0) {
                        if (weekly_offday.includes(date.getDay())) {
                            return [false];
                        }
                    }
                    return [true];
                }








    });






})(jQuery);