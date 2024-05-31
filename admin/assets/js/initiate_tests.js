const reload = initiate_tests_data.reload;
const sgnonce = initiate_tests_data.sgnonce;
const sg_run_one_test_nonce = initiate_tests_data.sg_run_one_test_nonce;
//console.log('sgnonce:' + sgnonce);
//console.log('sg_run_one_test_nonce:' + sg_run_one_test_nonce);


const check_tests_queue_status = async (ajaxurl, sgnonce, reload) => {
    console.log('sgnonce inside check_tests_queue_status:' + sgnonce);
    try {
        const response = await fetch(ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Cache-Control': 'no-cache',
                'Connection': 'keep-alive',
            },
            body: `action=check_tests_progress&nonce=${sgnonce}`,
        });

        const data = await response.json();
        console.log(data);

        if (data.status === 'queue') {
            console.log(data);
            // TODO If queue -> Run the test from here!
            setTimeout(() => check_tests_queue_status(ajaxurl, sgnonce, reload), 10000); //Update often only when there are tests in the queue
            //  console.log('Sending ID to test:');
            //  console.log(data.speedguard_test_in_progress_id);
            // console.log('Sending URL to test:');
            //  console.log(data.speedguard_test_in_progress_url);

            // Continue repeating the test while there are tests in the queue
            return sg_run_one_test(ajaxurl, data.speedguard_test_in_progress_url, sg_run_one_test_nonce, data.speedguard_test_in_progress_id);
        } else if (data.status === 'last_test_complete') {
            console.log('Tests complete, we can reload the page');
            if (reload === 'true') {
                window.location.replace(window.location.href + '&speedguard=load_time_updated');
            }
        } else if (data.status === 'no_tests') {
            console.log('No tests in the queue -- do nothing before next page update');
        } else {
            // Catch error
        }
    } catch (err) {
        console.log(err);
    }
};
// Start the process of Checking on page load
check_tests_queue_status(ajaxurl, sgnonce, reload);


// Run 1 Test and update its status
const sg_run_one_test = async (ajaxurl, url, sg_run_one_test, test_id) => {
    //comment to console log
    console.log('Firing sg_run_one_test with the following data:');
    console.log('ajaxurl:' + ajaxurl);
    console.log('sg_run_one_test got url:' + url);
    console.log('sg_run_one_test got test_id:' + test_id);
    console.log('sg_run_one_test got sg_run_one_test:' + sg_run_one_test);
    try {

        const test_result_data = await fetchAll(url);
        //Success
        if (typeof test_result_data === 'object') {
            console.log('Success. Test done, response is an object. Trying inside the function');
            // After successful API response -> Update test status as done

            try {
                console.log('Received response from PSI (fetchAll). Have these variables:');
                console.log('post_id' + test_id); //ok
                console.log('test_result_data' + test_result_data); //not ok
                console.log('test_result_data Stringify' + JSON.stringify(test_result_data)); //ok


                //Try mark_test_as_done
                // const ajaxurl = initiate_tests_data.ajaxurl;
                const test_result_data_string = JSON.stringify(test_result_data);
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Cache-Control': 'no-cache',
                        'Connection': 'keep-alive',
                    },
                    //Pass test data here
                    body: `action=mark_test_as_done&current_test_id=${test_id}&test_result_data=${test_result_data_string}&nonce=${sg_run_one_test}`,
                    //TODO another here?
                });
                if (response.ok) {  // Check if request was successful
                    console.log('Sent AJAX request with action mark_test_as_done successfully');

                    console.log(response);
                } else {
                    console.log('Failed to send AJAX request with action mark_test_as_done');
                }


            } catch (err) {
                console.log(err);
            }


        } else {
            console.log('Error: Response of fetchall is not object');
        }


    } catch (err) {
        console.log(err);
    }
}
