const {reload, sg_check_tests_queue_nonce: nonce, sg_run_one_test_nonce: run_nonce} = initiate_tests_data;

const check_tests_queue_status = async (ajaxurl, reload) => {
    try {
        console.log('Checking tests queue status...');

        const response = await fetch(ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Cache-Control': 'no-cache',
                'Connection': 'keep-alive',
            },
            body: `action=check_tests_progress&nonce=${nonce}`,
        });

        const data = await response.json();

        console.log('Received data:', data);

        if (data.status === 'queue') {
            console.log('Tests are in queue. Checking again in 10 seconds...');
            setTimeout(() => check_tests_queue_status(ajaxurl, reload), 10000);
            return sg_run_one_test(ajaxurl, data.speedguard_test_in_progress_url, run_nonce, data.speedguard_test_in_progress_id);
        } else if (data.status === 'last_test_complete' && reload === 'true') {
            console.log('Last test completed. Reloading page...');
            // set transient here

            window.location.replace(window.location.href + '&speedguard=load_time_updated');
        }
    } catch (err) {
        console.error('Error in check_tests_queue_status:', err);
    }
};

check_tests_queue_status(ajaxurl, reload);

const sg_run_one_test = async (ajaxurl, url, run_nonce, test_id) => {
    try {
        console.log('Running single test for URL:', url);

        const test_result_data = await fetchAll(url);

        console.log('Test result data:', test_result_data);

        if (typeof test_result_data === 'object') {
            try {
                const test_result_data_string = JSON.stringify(test_result_data);
                const mark_test_response = await fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Cache-Control': 'no-cache',
                        'Connection': 'keep-alive',
                    },
                    body: `action=mark_test_as_done&current_test_id=${test_id}&test_result_data=${test_result_data_string}&run_nonce=${run_nonce}`,
                });

                console.log('Mark test as done response:', mark_test_response);
            } catch (err) {
                console.error('Error marking test as done:', err);
            }
        }
    } catch (err) {
        console.error('Error in sg_run_one_test:', err);
    }
};
