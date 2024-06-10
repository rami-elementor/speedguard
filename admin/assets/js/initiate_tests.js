const {reload, sg_check_tests_queue_nonce: nonce, sg_run_one_test_nonce: run_nonce} = initiate_tests_data;

const check_tests_queue_status = async (ajaxurl, reload) => {
    try {
        const response = await fetch(ajaxurl, {
            method: 'POST', credentials: 'same-origin', headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Cache-Control': 'no-cache',
                'Connection': 'keep-alive',
            }, body: `action=check_tests_progress&nonce=${nonce}`,
        });

        const data = await response.json();

        if (data.status === 'queue') {
            setTimeout(() => check_tests_queue_status(ajaxurl, reload), 15000);
            return sg_run_one_test(ajaxurl, data.speedguard_test_in_progress_url, run_nonce, data.speedguard_test_in_progress_id);
        } else if (data.status === 'last_test_complete' && reload === 'true') {
            //set transient here

            window.location.replace(window.location.href + '&speedguard=load_time_updated');
        }
    } catch (err) {
        console.error(err);
    }
};

check_tests_queue_status(ajaxurl, reload);

const sg_run_one_test = async (ajaxurl, url, run_nonce, test_id) => {
    try {
        const test_result_data = await fetchAll(url);

        if (typeof test_result_data === 'object') {
            try {
                const test_result_data_string = JSON.stringify(test_result_data);
                await fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Cache-Control': 'no-cache',
                        'Connection': 'keep-alive',
                    },
                    body: `action=mark_test_as_done&current_test_id=${test_id}&test_result_data=${test_result_data_string}&run_nonce=${run_nonce}`,
                });
            } catch (err) {
                console.error(err);
            }
        }
    } catch (err) {
        console.error(err);
    }
}