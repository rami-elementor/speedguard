/**
 * SpeedGuard JavaScript for Running Tests
 */

async function fetchAll(url_to_test) {
    // What they chose in settings CWV or PSI doesn't matter here -- we retrieve both anyways
    const request_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?category=performance&url=' + url_to_test + '&';
    const devices = ['mobile', 'desktop'];

    try {
        singleURLresult = [];
        let tests = await Promise.all(
            //separate test for Device, but the same for PSI and CWV
            devices.map(device => fetch(request_url + 'strategy=' + device)
                .then(r => r.json())
                .catch(error => ({ error, url}))
            )
        )
        for (let item of tests) {

            //get current device value
            const device = item.lighthouseResult.configSettings.emulatedFormFactor;

            //Data for the Single URL (both CWV and PSI)
            const defaultValue = 'No data';
            const URL_RESULTS = {
                "cwv": {
                    "lcp": item?.loadingExperience?.metrics?.LARGEST_CONTENTFUL_PAINT_MS ?? defaultValue,
                    "cls": item?.loadingExperience?.metrics?.CUMULATIVE_LAYOUT_SHIFT_SCORE ?? defaultValue,
                    "fid": item?.loadingExperience?.metrics?.FIRST_INPUT_DELAY_MS ?? defaultValue,
                    "overall_category": item?.loadingExperience?.overall_category ?? defaultValue
                },
                "psi": {
                    "lcp": item?.lighthouseResult?.audits['largest-contentful-paint'] ?? defaultValue,
                    "cls": item?.lighthouseResult?.audits['cumulative-layout-shift'] ?? defaultValue
                }
            };

            console.log(URL_RESULTS);
            //Save data to the new object based on device value
            let singleURLresultperdevice = {
                [device]: {"psi": URL_RESULTS.psi, "cwv": URL_RESULTS.cwv}
            };
            singleURLresult.push(singleURLresultperdevice);
            console.log(singleURLresult);


//TODO for Origin
            //Data for CWV Origin
            const Origin_CWV = {

                "lcp": item?.originLoadingExperience?.metrics?.LARGEST_CONTENTFUL_PAINT_MS ?? defaultValue,
                "cls": item?.originLoadingExperience?.metrics?.CUMULATIVE_LAYOUT_SHIFT_SCORE ?? defaultValue,
                "fid": item?.originLoadingExperience?.metrics?.FIRST_INPUT_DELAY_MS ?? defaultValue,
                "overall_category": item?.originLoadingExperience?.overall_category ?? defaultValue
            };



        }


        return singleURLresult;
    } catch (err) {
        console.log('There was an errror:');
        console.log(err)
    }
}
