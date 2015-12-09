$("#enroll").submit(function() {
	
	if($("#Email").val() == '')
		$("#result").html("Please enter an email!");
	else {
		var url = location.origin+'/ajax/enroll';
	
		$("#loading").show();
		$("#submit").hide();
		$("#result").html("");
	
		$.ajax({
			type: "POST",
			url: url,
			dataType: 'json',
			data: $("#enroll").serialize(),
			success: function(response)
			{
				$("#loading").hide();
				$("#submit").show();
				var json = JSON.stringify(response);
				json = JSON.parse(json);
				if(json.status == 200) {
					$("#result").html(""+json.message);
					$("#Email").val('');
				}
				else if(json.status != 200) {
					$("#result").html(""+json.message);
				}
			}
		});
	}
	return false;
})

$(".student").click(function() {
	$(".student_progress").toggle();
	$("#loading2").show();
    var exists = document.getElementById("delete_"+this.id);
    if(!exists)
        $(".line").after("<button title=\"Delete\" id=\"delete_"+this.id+"\" name=\"delete\" class=\"delete button small alert\">Delete Student</button>");
	
	var url = location.origin+'/ajax/progress';
	
	$.ajax({
		type: "GET",
		url: url,
		dataType: 'json',
		data: { id : this.id },
		success: function(response)
		{
			$("#loading2").hide();
			if(response.length == 0)
				$(".placeholder").after("<p class='completed'> User has not completed any problems. </p>");
			else {
				for(var k in response) {
					var t = (response[k].finished).split(/[- :]/);
					var d = new Date(t[0], t[1]-1, t[2], t[3], t[4], t[5]);
					var day = d.getMonth() + '/' + d.getDate() + '/' + d.getYear();
					var curr_hour = d.getHours();
					if (curr_hour < 12)
					   a_p = "AM";
					else
					   a_p = "PM";
					if (curr_hour == 0)
					   curr_hour = 12;
					if (curr_hour > 12)
					   curr_hour = curr_hour - 12;
					var time = curr_hour + ':' + d.getMinutes();
					$(".placeholder").after("<p class='completed'><span class='title'>"+response[k].title+"</span><br><span class='finished' style=\"font-size:12px;color:#303030\"> Finished on: "+day+" at "+time+"</p>");
				}
			}
		}
	})
})

$('#students').bind('closed', function() {
    $( ".completed" ).remove();
    $( ".delete" ).remove();
    $( ".student_progress" ).hide();
});

$("body").on('click','.delete', function() {
	if(confirm("Are you sure you want to delete this student?\n\nThis will remove all traces of them from the website including all progress and account details.")) 
	{
		$("#loading2").show();
		$(".delete").hide();
		var id = (this.id).substring(7);
		var url = location.origin+'/ajax/delete'
		$.ajax({
			type: "DELETE",
			url: url,
			dataType: 'json',
			data: { id : id},
			success: function(response)
			{
				$("#loading2").hide();
				$(".delete").show();
				var json = JSON.stringify(response);
				json = JSON.parse(json);
				if(json.status == 200) {
					$("#"+id).remove();
					$("#result2").html(""+json.message);
				}
				else if(json.status != 200) {
					$("#result2").html(""+json.message);
				}
			}
		})
	}
})

$("#problem").submit(function() {
    
	if($("#Title").val() == '')
		$("#result3").html("Please enter a title.");
	else if($("#Prompt").val() == '')
		$("#result3").html("Please enter a prompt.");
	else if($("#Method").val() == '')
		$("#result3").html("Please enter the method.");
	else if($("#Test").val() == '')
		$("#result3").html("Please enter at least one test.");
	else if($("#Output").val() == '')
		$("#result3").html("Please enter expected output.");
	else {
	
		$("#loading3").show();
		$("#submit3").hide();
	
		var url = location.origin+'/ajax/problem';
        
        console.log($("#problem").serialize())
	
		$.ajax({
			type: "POST",
			url: url,
			dataType: 'json',
			data: $("#problem").serialize(),
			success: function(response)
			{
				$("#loading3").hide();
				$("#submit3").show();
				var json = JSON.stringify(response);
				json = JSON.parse(json);
				if(json.status == 200) {
                    console.log(json);
					$("#result3").html(""+json.message);
					$("#Title").val('');
					$("#Prompt").val('');
					$("#Method").val('');
					$("#Test").val('');
					$("#Output").val('');
                    $(".test_row_added").remove();
				}
				else if(json.status != 200) {
					$("#result3").html(""+json.message);
                    console.log(json.message);
				}
			},
            failure: function(response)
            {
				$("#loading3").hide();
				$("#submit3").show();
                var json = JSON.stringify(response);
                $("#result3").html(""+json.message);
            }
		})
	}
	return false;
});

$("#new_test").click(function() {
    var new_row = "<div class='row test_row_added'><div class='large-4 columns'><label style='text-align:left'>Test<input class='login' id='Test' name='test[]' type='text' placeholder='add(1,2)'></label></div><div class='large-4 columns'><label style='text-align:left'>Expected Output<input class='login' id='Output' name='output[]' type='text' placeholder='3'></label></div><div class='large-2 columns'><label>Hidden<select id='Hidden' name='hidden[]'><option value='0' selected>No</option><option value='1'>Yes</option></select></label></div><div class='large-2 columns'><label style='text-align:left' id='remove_test_button'><br><i class='fa fa-minus' id='remove_test' style='font-size: 25px'></i></label></div></div>";
    $("#test_row").after(new_row);
});

$(document).on('click', '#remove_test', function() {
    $(this).parent().parent().parent().remove();
});

$('#add_problem').bind('closed', function() {
    $(".test_row_added").remove();
});
