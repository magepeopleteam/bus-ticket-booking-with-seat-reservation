(function($) {
    'use strict';

    $(document).ready(function($) {

        var single_bus = $( "#all_date_picker_info" ).data( "single_bus" ) || '';
        var return_single_bus = $( "#return_all_date_picker_info" ).data( "return_single_bus" ) || '';
        var date_format = $( "#all_date_picker_info" ).data( "date_format" );

        if(single_bus){

            var enableDates = $( "#all_date_picker_info" ).data( "enabledates" );
            var off_particular_date = $( "#all_date_picker_info" ).attr( "data-off_particular_date" );
            var weekly_offday = $( "#all_date_picker_info" ).data( "weekly_offday" );
            var enable_onday = $( "#all_date_picker_info" ).data( "enable_onday" );
            var enable_offday = $( "#all_date_picker_info" ).data( "enable_offday" );


            if(enable_onday || enable_offday){
                if(enable_onday == 'yes') {
                    if(enableDates){
                        jQuery('#j_date').datepicker({
                            dateFormat: date_format,
                            beforeShowDay: function (date){
                                return enableAllTheseDays(date, enableDates );
                            }

                        });
                    }else{
                        jQuery("#j_date").datepicker({
                            dateFormat: date_format,
                            minDate: 0,
                        });
                    }

                } else if(enable_offday=='yes'){
                    jQuery("#j_date").datepicker({
                        dateFormat: date_format,
                        minDate: 0,
                        beforeShowDay: function (date){
                            return off_particular(date, off_particular_date,weekly_offday );
                        }
                    });
                }else{
                    jQuery("#j_date").datepicker({
                        dateFormat: date_format,
                        minDate: 0,
                    });
                }
            }else{
                if(enableDates){
                    jQuery('#j_date').datepicker({
                        dateFormat: date_format,
                        minDate: 0,
                        beforeShowDay: function (date){
                            return enableAllTheseDays(date, enableDates );
                        }
                    });
                }else{
                    jQuery("#j_date").datepicker({
                        dateFormat: date_format,
                        minDate: 0,
                        beforeShowDay: function (date){
                            return off_particular(date, off_particular_date,weekly_offday );
                        }
                    });
                }
            }

        }else{
            var global_off_particular_date = $( "#all_date_picker_info" ).data( "disabledates" );
            var global_weekly_offday = $( "#all_date_picker_info" ).data( "disabledays" );

            jQuery("#j_date, #r_date").datepicker({
                dateFormat: date_format,
                minDate: 0,
                beforeShowDay: function (date){
                    return off_particular(date, global_off_particular_date,global_weekly_offday );
                }
            });

        }




        if(return_single_bus){

            var return_enableDates = $( "#return_all_date_picker_info" ).attr( "data-enabledates" );
            var return_off_particular_date = $( "#return_all_date_picker_info" ).attr( "data-off_particular_date" );
            var return_weekly_offday = $( "#return_all_date_picker_info" ).attr( "data-weekly_offday" );
            var return_enable_onday = $("#return_all_date_picker_info").attr( "data-enable_onday" );
            var return_enable_offday = $( "#return_all_date_picker_info" ).attr( "data-enable_offday" );


            if(return_enable_onday || return_enable_offday){
                if(return_enable_onday == 'yes') {
                    if(return_enableDates){
                        jQuery('#r_date').datepicker({
                            dateFormat: date_format,
                            minDate: 0,
                            beforeShowDay: function (date){
                                return enableAllTheseDays(date, return_enableDates );
                            }
                        });
                    }else{
                        jQuery("#r_date").datepicker({
                            dateFormat: date_format,
                            minDate: 0,
                        });
                    }

                } else if(return_enable_offday=='yes'){
                    jQuery("#r_date").datepicker({
                        dateFormat: date_format,
                        minDate: 0,
                        beforeShowDay: function (date){
                            return off_particular(date, return_off_particular_date,return_weekly_offday );
                        }

                    });
                }else{
                    jQuery("#r_date").datepicker({
                        dateFormat: date_format,
                        minDate: 0,
                    });
                }
            }else{
                if(return_enableDates){
                    jQuery('#r_date').datepicker({
                        dateFormat: date_format,
                        minDate: 0,
                        beforeShowDay: function (date){
                            return enableAllTheseDays(date, return_enableDates );
                        }
                    });
                }else{
                    jQuery("#r_date").datepicker({
                        dateFormat: date_format,
                        minDate: 0,
                        beforeShowDay: function (date){
                            return off_particular(date, return_off_particular_date,return_weekly_offday );
                        }
                    });
                }
            }

        }

    });


    function enableAllTheseDays(date,enableDates) {
        var sdate = jQuery.datepicker.formatDate('yy-mm-dd', date)
        const p = enableDates.split(', ');
        if (p.length > 0) {
            if (jQuery.inArray(sdate, p) != -1) {
                return [true];
            }
        }
        return [false];
    }


    function off_particular(date,off_particular_date,weekly_offday) {
        var sdate = jQuery.datepicker.formatDate('dd-mm-yy', date)
        const p = off_particular_date.split(',')
        if (p.length > 0) {
            if (jQuery.inArray(sdate, p) != -1) {
                return [false];
            }
        }
        let d;
        weekly_offday = weekly_offday.toString();
        if (weekly_offday.indexOf(',') != -1) {
            d = weekly_offday.split(',')
            
        } else {
            d = [weekly_offday]
        }
        if (d.length > 0) {
            if (d.includes(date.getDay().toString())) {
                return [false];
            }
        }
        return [true];
    }


})(jQuery);