/**
 * SpeedGuard JavaScript for Running Tests
 */

async function fetchAll(url_to_test) {
    const request_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?category=performance&url=' + url_to_test + '&';
    const devices = ['mobile', 'desktop'];
    const defaultValue = 'No data';
    let singleURLresult = [];

    try {
        singleURLresult = await Promise.all(devices.map(async device => {
            try {
                const response = await fetch(request_url + 'strategy=' + device);

                if (!response.ok) {
                    console.error(`HTTP error ${response.status} occurred for ${device}`);
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
                        "lcp": item?.lighthouseResult?.audits['largest-contentful-paint']?.displayValue ?? defaultValue,
                        "cls": item?.lighthouseResult?.audits['cumulative-layout-shift']?.displayValue ?? defaultValue
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

                return singleURLresultperdevice;
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
                return {
                    [device]: {
                        "psi": URL_RESULTS.psi,
                        "cwv": URL_RESULTS.cwv,
                        "originCWV": URL_RESULTS.originCWV
                    }
                };
            }
        }));

    } catch (err) {
        console.error('There was an error:', err);
    }

    return singleURLresult;
}
