
jQuery(document).ready(function() {
	window.course_structure = manual_completions_tutor.course_structure;

    jQuery('#manual_completions_tutor select.en_select2').select2({width: "100%"});

	jQuery("#select_all").on("change", function() {
		jQuery("tr[data-completion] [type=checkbox]:not([disabled])").prop('checked', jQuery("#select_all").is(":checked"));
		manual_completions_tutor_update_checked_count();
	});

	jQuery("#manual_completions_tutor_table table").click(function(e) {
		if(jQuery(e.target).attr("type") == "checkbox") {
			manual_completions_tutor_update_checked_count();
		}
	});

	jQuery('#users [role="searchbox"]').on("keypress", function(e) {
		manual_completions_tutor_handle_users_keypress(e, ",");
		manual_completions_tutor_handle_users_keypress(e, " ");
	});
	jQuery('#users [role="searchbox"]').on("keyup change", function() {
		manual_completions_tutor_filter_users_list();
	});

	if(typeof manual_completions_tutor.uploaded_data == "object" && manual_completions_tutor.uploaded_data.length > 0) {
		jQuery("#manual_completions_tutor #course").hide();

		jQuery.each(manual_completions_tutor.uploaded_data, function(i, data) {
			manual_completions_tutor_add_row(data, i+1);
		});
	}
});
function manual_completions_tutor_update_checked_count() {
	jQuery("#process_completions .count, #check_completions .count").text(" (" + jQuery("#manual_completions_tutor_table input[type=checkbox]:not(#select_all):checked").length + ")");
}
function manual_completions_tutor_show_user_selection(show) {
	if(show) {
		jQuery('#users').show();
	}
	else
	{
		jQuery('#users').hide();
	}
}
function manual_completions_tutor_filter_users_list() {
	var string = jQuery('#users [role="searchbox"]').val().trim();
	if(typeof window.manual_completions_tutor_filter_users_string  == "string" && window.manual_completions_tutor_filter_users_string == string)
	return;

	window.manual_completions_tutor_filter_users_string = string;

	if( string == "" ) {
		jQuery("#users option").show();
	}
	else {
		var select = "";
		var count = 0;
		jQuery("#users option").each(function(i, option) {
			if( jQuery(option).val() == "" ) {
				select = jQuery(option);
				select.show();
				return;
			}

			if( jQuery(option).text().toLowerCase().indexOf(string) != -1 ) {
				jQuery(option).show();
				count++;
			}
			else
			jQuery(option).hide();
		});
		if( select ) {
			jQuery(select).html("Updating...");
			setTimeout(function(){ jQuery(select).html(" --- Select a User --- (" + count + ")"); }, 200);
			//console.log(count);
		}
	}
}
function manual_completions_tutor_handle_users_keypress(e, splitter) {
    	//console.log(e.which, jQuery("#user_ids").val(), jQuery('#users [role="searchbox"]').val());
    	if(e.which == 32 || e.which == 13) {
			var values = jQuery("#user_ids").val();
			if(values == null)
				values = [];

			var string = jQuery('#users [role="searchbox"]').val();
			var updated = false;
			var input_items = string.split(splitter);
			jQuery.each(input_items, function(i, v) {
				if(v > 0) {
					var value = jQuery("#user_ids option[value=" + v.trim() + "]").val();
					if(value != undefined) {
						updated = true;
						values[values.length] = value;
						jQuery("#user_ids").val(value).trigger("change");
						delete( input_items[i] );
					}
				}
				else
				{
					delete( input_items[i] );
				}
			});
			if( updated ) {
			jQuery('#users [role="searchbox"]').val(input_items.filter(function(el) { return el; }).join(splitter));
	// 		if( jQuery('#users [role="searchbox"]').data("events")  == undefined || jQuery('#users [role="searchbox"]').data("events")["keypress"] == undefined )
	// 		{
	// 			jQuery('#users [role="searchbox"]').on("keypress", function(e) {
	// 				manual_completions_tutor_handle_users_keypress(e, ",");
	// 				manual_completions_tutor_handle_users_keypress(e, " ");
	// 			});
	// 		}
		}
	}
}
function manual_completions_tutor_course_selected(context) {
	var course_id = jQuery(context).val();
	manual_completions_tutor_clear_value("topic");
	manual_completions_tutor_clear_value("lesson");
	manual_completions_tutor_clear_value("quiz");
	if(typeof course_structure[course_id] == "object") {
		manual_completions_tutor_show_elements( course_structure[course_id] );
		return;
	}
	jQuery("#manual_completions_tutor #lesson, #manual_completions_tutor #quiz").hide();
	jQuery("#manual_completions_tutor #quiz option.auto").remove();

	if( course_id == "" || course_id == null ) {
		jQuery("#manual_completions_tutor #upload_csv").show();
		return;
	}
	else
		jQuery("#manual_completions_tutor #upload_csv").hide();

	var data = {
		"action" : "manual_completions_tutor_course_selected",
		"course_id" : course_id
	};
	jQuery("#course #gb_loading_animation").show();
	jQuery.post(manual_completions_tutor.ajax_url, data)
	.done(function( data ) {
		console.error(data);
		if( data.status == 1 && typeof data.data == "object" ) {
			jQuery("#course #gb_loading_animation").hide();
			course_structure[course_id] = data.data;
			manual_completions_tutor_show_elements( course_structure[course_id] );
			return;
		}
		else{
			alert("Invalid course data received");
			jQuery("#course #gb_loading_animation").hide();
		}
	})
	.fail(function(xhr, status, error) {
		console.log(xhr, status, error);
		alert("Request to get course data failed");
		jQuery("#course #gb_loading_animation").hide();
	});
}
function manual_completions_tutor_quiz_selected(context) {
	if(jQuery("#manual_completions_tutor #quiz option:selected").hasClass("global"))
	{
		manual_completions_tutor_unselect_value("lesson");
	}
	var quiz_id = jQuery("#manual_completions_tutor #quiz_id").val();
	manual_completions_tutor_show_user_selection(quiz_id > 0);
}
function manual_completions_tutor_topic_selected(context) {
	var id = jQuery("#manual_completions_tutor #topic_id").val();

	manual_completions_tutor_clear_value("lesson");
	manual_completions_tutor_clear_value("quiz");

	if(id > 0 || id == "all"){
		manual_completions_tutor_show_elements();
		manual_completions_tutor_show_user_selection(true);
	}
}
function manual_completions_tutor_lesson_selected(context) {
	var id = jQuery("#manual_completions_tutor #lesson_id").val();

	if(id > 0 || !jQuery("#manual_completions_tutor #quiz option:selected").hasClass("global"))
		manual_completions_tutor_clear_value("quiz");

	manual_completions_tutor_show_elements();

	if(id > 0 || id == "all")
		manual_completions_tutor_show_user_selection(true);
}
function manual_completions_tutor_unselect_value(name) {
	if(jQuery("#manual_completions_tutor #" + name + "_id").val() != "")
		jQuery("#manual_completions_tutor #" + name + "_id").val("").trigger("change");
}
function manual_completions_tutor_clear_value(name) {
	manual_completions_tutor_unselect_value(name);

	jQuery("#manual_completions_tutor #" + name + " option.auto:not(.global)").remove();
	if(jQuery("#manual_completions_tutor #" + name + " option").length <= 1 || name == "lesson" && jQuery("#manual_completions_tutor #quiz option").length <= 2)
		jQuery("#manual_completions_tutor #" + name).hide();
}
function manual_completions_tutor_show_elements(data) {
	var course_id = jQuery("#manual_completions_tutor #course_id").val();

	if(data == undefined && typeof course_structure[course_id] != "object")
		return;

	var course_id = jQuery("#manual_completions_tutor #course_id").val();
	var topic_id = jQuery("#manual_completions_tutor #topic_id").val();
	var lesson_id = jQuery("#manual_completions_tutor #lesson_id").val();
	var quiz_id = jQuery("#manual_completions_tutor #quiz_id").val();

	if(typeof data != "object") {
		data = course_structure[course_id];
	}
	if(typeof data != "object") {
		console.error("Invalid data");
		alert("Invalid data");
		return;
	}

	if(typeof data["topics"] == "object" && topic_id == "") {
		manual_completions_tutor_clear_value("topic");
		manual_completions_tutor_clear_value("lesson");
		manual_completions_tutor_clear_value("quiz");

		jQuery.each(data["topics"], function(topic_id, topic_data) {
			jQuery("#manual_completions_tutor #topic_id").append("<option class='auto' value='" + topic_id + "'>" + topic_data.topic["post_title"] + "</option>");
		});

		jQuery("#manual_completions_tutor #topic").show();
	}

	if(typeof data["topics"] == "object" && topic_id > 0) {

		if(typeof data["topics"][topic_id] == "object" && typeof data["topics"][topic_id]["lessons"] == "object" && lesson_id == "") {
			manual_completions_tutor_clear_value("lesson");

			jQuery.each(data["topics"][topic_id]["lessons"], function(lesson_id, lesson_data) {
				jQuery("#manual_completions_tutor #lesson_id").append("<option class='auto' value='" + lesson_id + "' " + manual_completions_tutor_has_xapi_attr(lesson_data) +  ">" + lesson_data.lesson["post_title"] + " " + manual_completions_tutor_has_xapi_label(lesson_data) + "</option>");
			});
			jQuery("#manual_completions_tutor #lesson").show();
		}
		if(typeof data["topics"][topic_id] == "object" && typeof data["topics"][topic_id]["quizzes"] == "object" && lesson_id == "") {
			manual_completions_tutor_clear_value("quiz");

			jQuery.each(data["topics"][topic_id]["quizzes"], function(quiz_id, quiz_data) {
				jQuery("#manual_completions_tutor #quiz_id").append("<option class='auto' value='" + quiz_id + "' " + manual_completions_tutor_has_xapi_attr(quiz_data) +  ">" + quiz_data.quiz["post_title"] + " " + manual_completions_tutor_has_xapi_label(quiz_data) + "</option>");
			});
			jQuery("#manual_completions_tutor #quiz").show();
		}
	}

	if( lesson_id > 0 || quiz_id > 0 )
		manual_completions_tutor_show_user_selection(true);
	else
		manual_completions_tutor_show_user_selection(false);
}
function manual_completions_tutor_has_xapi_label(data) {
	if(typeof data == "object" && typeof data.xapi_content == "object")
		return " (has xAPI Content) ";
	else
		return "";
}
function manual_completions_tutor_has_xapi_attr(data) {
	if(typeof data == "object" && typeof data.xapi_content == "object")
		return " data-xapi='1' ";
	else
		return "";
}
function manual_completions_tutor_xapi_icon(name, data) {
	var course_id 	= (typeof data.course_id == "undefined")? "":data.course_id;
	var topic_id 	= (typeof data.topic_id == "undefined")? "":data.topic_id;
	var quiz_id 	= (typeof data.quiz_id == "undefined")? "":data.quiz_id;
	var lesson_id 	= (typeof data.lesson_id == "undefined")? "":data.lesson_id;

	if(typeof course_structure[course_id] != "object")
		return " ";

	switch(name) {
		case "lesson":
			if(lesson_id == "" || topic_id == "" || typeof course_structure[course_id]["topics"][topic_id] != "object" ||
				typeof course_structure[course_id]["topics"][topic_id]['lessons'] != "object" || typeof course_structure[course_id]["topics"][topic_id]['lessons'][lesson_id] != "object" ||
				typeof course_structure[course_id]["topics"][topic_id]['lessons'][lesson_id].xapi_content != "object" )
				return " ";
			else
				return " <span class='has_xapi' title='Has xAPI'></span> ";
		case "quiz":
			if(quiz_id == "" || topic_id == "" || typeof course_structure[course_id]["topics"][topic_id] != "object" ||
				typeof course_structure[course_id]["topics"][topic_id]['quizzes'] != "object" || typeof course_structure[course_id]["topics"][topic_id]['quizzes'][quiz_id] != "object" ||
				typeof course_structure[course_id]["topics"][topic_id]['quizzes'][quiz_id].xapi_content != "object" )
			return " ";
			else
				return " <span class='has_xapi' title='Has xAPI'></span> ";

	}
	return " ";
}
function manual_completions_tutor_users_selected(context) {
	var course_id = jQuery("#manual_completions_tutor #course_id").val();
	var topic_id = jQuery("#manual_completions_tutor #topic_id").val();
	var lesson_id = jQuery("#manual_completions_tutor #lesson_id").val();
	var quiz_id = jQuery("#manual_completions_tutor #quiz_id").val();

	//console.log(jQuery("#users select").val());
	var user_ids = jQuery("#users select").val();
	user_ids = (typeof user_ids != "object" && user_ids * 1 > 0)? [user_ids]:user_ids;

	var sno = jQuery("#manual_completions_tutor_table table tr:last-child .sno").text()*1 + 1;

	if(typeof user_ids == "object" && user_ids != null && user_ids.length > 0)
	jQuery.each(user_ids, function(i, user_id) {
		if( user_id > 0 ) {
			var data = {course_id:course_id, topic_id:topic_id, lesson_id:lesson_id, quiz_id:quiz_id, user_id: user_id};
			sno += manual_completions_tutor_add_row(data, sno);
		}
	});

	jQuery("#users select").val("");
}
function manual_completions_tutor_add_row(data, sno) {
	var course_id 	= (typeof data.course_id == "undefined")? "":data.course_id;
	var topic_id 	= (typeof data.topic_id == "undefined")? "":data.topic_id;
	var user_id 	= (typeof data.user_id == "undefined")? "":data.user_id;
	var quiz_id 	= (typeof data.quiz_id == "undefined")? "":data.quiz_id;
	var lesson_id 	= (typeof data.lesson_id == "undefined")? "":data.lesson_id;

	if(typeof course_structure[course_id] == "undefined"
		|| topic_id == "" && lesson_id > 0
		&& (typeof course_structure[course_id]["topics"] == "undefined" || typeof course_structure[course_id]["topics"][topic_id] == "undefined") || lesson_id > 0
		&& (typeof course_structure[course_id]["topics"][topic_id]["lessons"] == "undefined" || typeof course_structure[course_id]["topics"][topic_id]["lessons"][lesson_id] == "undefined")
	){
		console.log("Invalid row: ", data);
		return;
	}

	var key = "completion_" + course_id + "_" + topic_id + "_" + lesson_id + "_" + quiz_id + "_" + user_id;
	data["row_id"] = key;

	var row = "<tr id='" + key + "' data-completion='" + JSON.stringify(data) + "'>";

	if(jQuery("#manual_completions_tutor_table #" + key).length == 0)
	{
		var user_label = jQuery("#users option[value=" + user_id+ "]").text();
		if(user_label == "")
			user_label = user_id + " (User Not Found)";

		row += "<td>" + "<input type='checkbox' name='" + key + "'>" + "</td>";
		row += "<td class='sno'>" + sno + "</td>";
		row += "<td>" + user_label + "</td>";
		row += "<td>" + manual_completions_tutor_get_label("course", course_id, topic_id, lesson_id, quiz_id) + "</td>";
		row += "<td>" + manual_completions_tutor_get_label("topic", course_id, topic_id, lesson_id, quiz_id) + "</td>";
		row += "<td>" + manual_completions_tutor_xapi_icon("lesson", data) + manual_completions_tutor_get_label("lesson", course_id, topic_id, lesson_id, quiz_id) + "</td>";
		row += "<td>" + manual_completions_tutor_xapi_icon("quiz", data) 	+  manual_completions_tutor_get_label("quiz", course_id, topic_id, lesson_id, quiz_id) + "</td>";
		row += "<td>" + manual_completions_tutor_get_mark_complete_button(data) + "</td>";
		row += "<td class='status'>" + "Not Processed" + "</td>";

		if(jQuery(row).find(".has_xapi").length)
			jQuery("#manual_completions_tutor_table .force_completion").slideDown();

		jQuery("#manual_completions_tutor_table table").append(row);
		manual_completions_tutor_update_total_count();
		return true;
	}

	return false;
}
function manual_completions_tutor_update_total_count() {
	jQuery("#manual_completions_tutor_table #list_count .count").text(jQuery("#manual_completions_tutor_table tr").length - 1);
}
function manual_completions_tutor_get_mark_complete_button(data) {
	return " <a onclick='manual_completions_tutor_mark_complete(this)' class='button-secondary'>Mark Complete</a> " + " <a onclick='manual_completions_tutor_check_completion(this)' class='button-secondary'>Check Completion</a> " +  " <a onclick='manual_completions_tutor_remove(this);' class='button-secondary'> X </a> ";
}
function manual_completions_tutor_remove(context) {
	jQuery(context).closest("tr").attr("data-status", "remove");

	setTimeout(function() {
		jQuery(context).closest("tr").remove();
		manual_completions_tutor_update_checked_count();
		manual_completions_tutor_update_total_count();
	}, 600);
}
function manual_completions_tutor_mark_complete(selected) {

	if( jQuery("#manual_completions_tutor_table tr[data-status=processing]").length > 0 )
	{
		alert("Please wait for current queue to complete.");
		return;
	}

	var completion_data = [];

	if( selected != undefined )
		var selected_completions = jQuery(selected).closest("tr");
	else
		var selected_completions = jQuery("#manual_completions_tutor_table input[type=checkbox]:not(#select_all):checked").closest("tr");

	selected_completions.attr("data-status", "waiting");
	selected_completions.find(".status").text("Waiting...");

	var processing_completions = selected_completions.slice(0, 10);

	processing_completions.each(function(i, context) {
		completion_data[i] = jQuery(context).data("completion");

		jQuery(context).attr("data-status", "processing");
		jQuery(context).find(".status").text("Processing...");
		jQuery(context).find("input[type=checkbox]").prop("checked", false).prop("disabled", true);
	});

	if(typeof completion_data != "object" || completion_data == null || completion_data.length == 0) {
		alert("Nothing to process.");
		return;
	}

	var data = {
		"action" : "manual_completions_tutor_mark_complete",
		"data" : completion_data,
		"force_completion" : (jQuery("#force_completion").is(":checked")? 1:0)
	};
	jQuery.post(manual_completions_tutor.ajax_url, data)
	.done(function( data ) {
		console.error(data);

		if(typeof data.data == "object")
		jQuery.each(data.data, function(i, data) {
			var context = "#" + data.row_id;
			if( data.status == 1 )
				jQuery(context).closest("tr").attr("data-status", "processed");
			else
				jQuery(context).closest("tr").attr("data-status", "failed");

			if(typeof data.message == "string")
				jQuery(context).closest("tr").find(".status").text(data.message);
			else
				jQuery(context).closest("tr").find(".status").text("Invalid Response");
		});
	})
	.fail(function(xhr, status, error) {
		console.log(xhr, status, error);
	//	jQuery(context).closest("tr").find(".status").text("Request Failed");
		processing_completions.find(".status").text("Failed Request");
		processing_completions.attr("data-status", "failed");
	})
	.always(function() {
		manual_completions_tutor_update_checked_count();

		setTimeout(function() {

			var waiting = jQuery("#manual_completions_tutor_table tr[data-status=waiting]");
			if(waiting.length > 0)
			manual_completions_tutor_mark_complete( waiting );
			else if( selected == undefined )
			alert("All Completions Processed.");

		}, 500);
	});
}
function manual_completions_tutor_check_completion(selected) {

	if( jQuery("#manual_completions_tutor_table tr[data-status=processing]").length > 0 )
	{
		alert("Please wait for current queue to complete.");
		return;
	}

	var completion_data = [];

	if( selected != undefined )
		var selected_completions = jQuery(selected).closest("tr");
	else
		var selected_completions = jQuery("#manual_completions_tutor_table input[type=checkbox]:not(#select_all):checked").closest("tr");

	selected_completions.attr("data-status", "waiting");
	selected_completions.find(".status").text("Waiting...");

	var processing_completions = selected_completions.slice(0, 10);

	processing_completions.each(function(i, context) {
		completion_data[i] = jQuery(context).data("completion");

		jQuery(context).attr("data-status", "processing");
		jQuery(context).find(".status").text("Processing...");
		jQuery(context).find("input[type=checkbox]").prop("checked", false).prop("disabled", true);
	});

	if(typeof completion_data != "object" || completion_data == null || completion_data.length == 0) {
		alert("Nothing to process.");
		return;
	}

	var data = {
		"action" : "manual_completions_tutor_check_completion",
		"data" : completion_data
	};
	jQuery.post(manual_completions_tutor.ajax_url, data)
	.done(function( data ) {
		console.error(data);

		if(typeof data.data == "object")
		jQuery.each(data.data, function(i, data) {
			var context = "#" + data.row_id;
			if( data.status == 1 )
				jQuery(context).closest("tr").attr("data-status", "checked");
			else
				jQuery(context).closest("tr").attr("data-status", "failed");

			if(typeof data.message == "string")
				jQuery(context).closest("tr").find(".status").text(data.message);
			else
				jQuery(context).closest("tr").find(".status").text("Invalid Response");

			if(typeof data.completed != "undefined")
				jQuery(context).closest("tr").attr("data-completed", data.completed? "completed":"not_completed");

			if( data.completed != 1 )
				jQuery(context).find("input[type=checkbox]").prop("disabled", false);
		});

		jQuery("#manual_completions_tutor_table tr[data-status=processing]").find(".status").text("Unknown Response");
		jQuery("#manual_completions_tutor_table tr[data-status=processing]").attr("data-status", "failed");
		jQuery("#manual_completions_tutor_table tr[data-status=processing] input[type=checkbox]").prop("disabled", false);

	})
	.fail(function(xhr, status, error) {
		console.log(xhr, status, error);
	//	jQuery(context).closest("tr").find(".status").text("Request Failed");
		processing_completions.find(".status").text("Failed Request");
		processing_completions.attr("data-status", "failed");
		processing_completions.find("input[type=checkbox]").prop("disabled", false);
	})
	.always(function() {
		manual_completions_tutor_update_checked_count();

		setTimeout(function() {

			var waiting = jQuery("#manual_completions_tutor_table tr[data-status=waiting]");
			if(waiting.length > 0)
			manual_completions_tutor_check_completion( waiting );
			else if( selected == undefined )
			alert("All requests processed.");

		}, 500);
	});
}
function manual_completions_tutor_get_label(name, course_id, topic_id, lesson_id, quiz_id) {

	switch(name) {
		case "course" :
				return course_id + ". " + course_structure[course_id].course.post_title;
		case "topic" :
				if(topic_id == "all")
					return "-- Entire Course --";

				if(topic_id == "" || topic_id == null)
					return topic_id;

				if(typeof course_structure[course_id]['topics'] == "object" && typeof course_structure[course_id]['topics'][topic_id] == "object" )
					return topic_id + ". " + course_structure[course_id]['topics'][topic_id].topic.post_title;

				return topic_id;
		case "lesson" :
				if(lesson_id == "all")
				{
					return "-- Entire Topic --";
				}
				return (lesson_id == "" || lesson_id == null)? lesson_id:lesson_id + ". " + course_structure[course_id]['topics'][topic_id]["lessons"][lesson_id].lesson.post_title;
		case "quiz" :
				if(quiz_id == "" || quiz_id == null)
					return quiz_id;

				if(typeof course_structure[course_id]['topics'][topic_id].quizzes == "object" && typeof course_structure[course_id]['topics'][topic_id].quizzes[quiz_id] == "object" )
					return quiz_id + ". " + course_structure[course_id]['topics'][topic_id].quizzes[quiz_id].quiz.post_title;

				return quiz_id;
	}
	return "";
}

function grassblade_tutor_plugin_activate_deactivate(el, url) {
	el.children("#gb_loading_animation").show();
	jQuery.get(url, function(data) {
		el.children("#gb_loading_animation").show();
		window.location.reload();
	});
	return false;
}

function manual_completions_tutor_get_enrolled_users() {
	var course_id = jQuery("#course_id").val();
	var topic_id = jQuery("#topic_id").val();
	var lesson_id = jQuery("#lesson_id").val();
	var quiz_id = jQuery("#quiz_id").val();

	if(course_id == "")
		return;

	if(lesson_id == "" && quiz_id == "")
		topic_id = "all";

	var data = {
		"action" : "manual_completions_tutor_get_enrolled_users",
		"course_id" : course_id,
	};

	jQuery.post(manual_completions_tutor.ajax_url, data)
	.done(function( data ) {
		//console.error(data);
		var old_sno = jQuery("#manual_completions_tutor_table tr:last .sno").text()*1;
		var sno = 0;
		if(typeof data.data == "object")
		jQuery.each(data.data, function(i, user_id) {
			var d = {
				user_id: user_id,
				course_id: data.course_id,
				topic_id: topic_id,
				lesson_id: lesson_id,
				quiz_id: quiz_id,
			};
			manual_completions_tutor_add_row(d, old_sno + ++sno);
		});

		if(sno > 0)
			alert("Found " + sno + " users.");
		else
			alert("No users found");
	});
}