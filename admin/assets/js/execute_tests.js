/**
 * SpeedGuard JavaScript for Running Tests
 */

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function fetchAll(url_to_test) {
    const request_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?category=performance&url=' + url_to_test + '&';
    const devices = ['mobile', 'desktop'];
    const defaultValue = 'No data';
    const delayBetweenRequests = 1500; // delay in milliseconds
    const retryDelay = 2000; // retry delay in milliseconds

    let singleURLresult = [];
    let hadSuccessfulRequest = false;
    let failedDevices = [];

    try {
        for (const device of devices) {
            const deviceRequestUrl = request_url + 'strategy=' + device;
            console.log(`Requesting URL for device ${device}: ${deviceRequestUrl}`); // Logging the request URL
            //SZ try to put it here
            await sleep(delayBetweenRequests);
            try {
                const response = await fetch(deviceRequestUrl);
                if (!response.ok) {
                    if (response.status === 400) {
                        console.error(`HTTP 400 error occurred for ${device}`);
                        failedDevices.push(device);
                    } else {
                        console.error(`HTTP error ${response.status} occurred for ${device}`);
                        throw new Error(`HTTP error ${response.status}`);
                    }
                } else {

                    const item = await response.json();

                    // get current device value
                    const currentDevice = item.lighthouseResult.configSettings.emulatedFormFactor;

                    // Data for the Single URL (both CWV and PSI)
                    const URL_RESULTS = {
                        "cwv": {
                            "lcp": item?.loadingExperience?.metrics?.LARGEST_CONTENTFUL_PAINT_MS ?? defaultValue,
                            "cls": item?.loadingExperience?.metrics?.CUMULATIVE_LAYOUT_SHIFT_SCORE ?? defaultValue,
                            "inp": item?.loadingExperience?.metrics?.INTERACTION_TO_NEXT_PAINT ?? defaultValue,
                            "overall_category": item?.loadingExperience?.overall_category ?? defaultValue
                        },
                        "psi": {
                            "lcp": item?.lighthouseResult?.audits['largest-contentful-paint'] ?? defaultValue,
                            "cls": item?.lighthouseResult?.audits['cumulative-layout-shift'] ?? defaultValue
                        },
                        "originCWV": {
                            "lcp": item?.originLoadingExperience?.metrics?.LARGEST_CONTENTFUL_PAINT_MS ?? defaultValue,
                            "cls": item?.originLoadingExperience?.metrics?.CUMULATIVE_LAYOUT_SHIFT_SCORE ?? defaultValue,
                            "inp": item?.originLoadingExperience?.metrics?.INTERACTION_TO_NEXT_PAINT ?? defaultValue,
                            "overall_category": item?.originLoadingExperience?.overall_category ?? defaultValue
                        }
                    };

                    // Save data to the new object based on device value
                    let singleURLresultperdevice = {
                        [currentDevice]: {
                            "psi": URL_RESULTS.psi,
                            "cwv": URL_RESULTS.cwv,
                            "originCWV": URL_RESULTS.originCWV
                        }
                    };

                    singleURLresult.push(singleURLresultperdevice);
                    hadSuccessfulRequest = true;

                }
            } catch (error) {
                console.error(`An error occurred for ${device}:`, error.message);

                // If there's an error, return object with default values
                const URL_RESULTS = {
                    "cwv": {
                        "lcp": defaultValue,
                        "cls": defaultValue,
                        "inp": defaultValue,
                        "overall_category": defaultValue
                    },
                    "psi": {
                        "lcp": defaultValue,
                        "cls": defaultValue
                    },
                    "originCWV": {
                        "lcp": defaultValue,
                        "cls": defaultValue,
                        "inp": defaultValue,
                        "overall_category": defaultValue
                    }
                };

                // Return object with default values for the current device
                let singleURLresultperdevice = {
                    [device]: {
                        "psi": URL_RESULTS.psi,
                        "cwv": URL_RESULTS.cwv,
                        "originCWV": URL_RESULTS.originCWV
                    }
                };

                singleURLresult.push(singleURLresultperdevice);
            }

            // Delay between requests
            await sleep(delayBetweenRequests);
        }

        // Retry failed requests with a 400 error if there was at least one successful request
        if (hadSuccessfulRequest && failedDevices.length > 0) {
            console.log(`Retrying ${failedDevices.length} failed request(s) after delay of ${retryDelay} ms...`);
            await sleep(retryDelay);

            for (const device of failedDevices) {
                const deviceRequestUrl = request_url + 'strategy=' + device;
                console.log(`Retrying URL for device ${device}: ${deviceRequestUrl}`); // Logging the request URL

                try {
                    const response = await fetch(deviceRequestUrl);
                    if (!response.ok) {
                        console.error(`HTTP error ${response.status} occurred for ${device} on retry`);
                        throw new Error(`HTTP error ${response.status}`);
                    }

                    const item = await response.json();

                    // get current device value
                    const currentDevice = item.lighthouseResult.configSettings.emulatedFormFactor;

                    // Data for the Single URL (both CWV and PSI)
                    const URL_RESULTS = {
                        "cwv": {
                            "lcp": item?.loadingExperience?.metrics?.LARGEST_CONTENTFUL_PAINT_MS ?? defaultValue,
                            "cls": item?.loadingExperience?.metrics?.CUMULATIVE_LAYOUT_SHIFT_SCORE ?? defaultValue,
                            "inp": item?.loadingExperience?.metrics?.INTERACTION_TO_NEXT_PAINT ?? defaultValue,
                            "overall_category": item?.loadingExperience?.overall_category ?? defaultValue
                        },
                        "psi": {
                            "lcp": item?.lighthouseResult?.audits['largest-contentful-paint'] ?? defaultValue,
                            "cls": item?.lighthouseResult?.audits['cumulative-layout-shift'] ?? defaultValue
                        },
                        "originCWV": {
                            "lcp": item?.originLoadingExperience?.metrics?.LARGEST_CONTENTFUL_PAINT_MS ?? defaultValue,
                            "cls": item?.originLoadingExperience?.metrics?.CUMULATIVE_LAYOUT_SHIFT_SCORE ?? defaultValue,
                            "inp": item?.originLoadingExperience?.metrics?.INTERACTION_TO_NEXT_PAINT ?? defaultValue,
                            "overall_category": item?.originLoadingExperience?.overall_category ?? defaultValue
                        }
                    };

                    // Save data to the new object based on device value
                    let singleURLresultperdevice = {
                        [currentDevice]: {
                            "psi": URL_RESULTS.psi,
                            "cwv": URL_RESULTS.cwv,
                            "originCWV": URL_RESULTS.originCWV
                        }
                    };

                    singleURLresult.push(singleURLresultperdevice);
                } catch (error) {
                    console.error(`An error occurred for ${device} on retry:`, error.message);

                    // If there's an error, return object with default values
                    const URL_RESULTS = {
                        "cwv": {
                            "lcp": defaultValue,
                            "cls": defaultValue,
                            "inp": defaultValue,
                            "overall_category": defaultValue
                        },
                        "psi": {
                            "lcp": defaultValue,
                            "cls": defaultValue
                        },
                        "originCWV": {
                            "lcp": defaultValue,
                            "cls": defaultValue,
                            "inp": defaultValue,
                            "overall_category": defaultValue
                        }
                    };

                    // Return object with default values for the current device
                    let singleURLresultperdevice = {
                        [device]: {
                            "psi": URL_RESULTS.psi,
                            "cwv": URL_RESULTS.cwv,
                            "originCWV": URL_RESULTS.originCWV
                        }
                    };

                    singleURLresult.push(singleURLresultperdevice);
                }

                // Delay between retry requests
                await sleep(delayBetweenRequests);
            }
        }

    } catch (err) {
        console.error('There was an error:', err);
    }

    return singleURLresult;
}
