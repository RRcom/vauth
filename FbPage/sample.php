<?php
// To get all user pages run this code.
$accountResultArray = $this->vauth->fb_get_accounts();
// If success the result will be an array of AccountResult object

// To get FbPage object, first we need to get a single AccountResult object from an array of AccountResult
$accountResult = $accountResultArray[0];

// Then we need to call fetchObject method to get FbPage object
$fbPage = $accountResult->fetchObject();

// Now that we have FbPage object we can now list all the tab the page has
$listOfTab = $fbPage->getTabs();
// listOfTab is an array of PageTab object

// We now can also add tab to a page
$result = $fbPage->addTab($appId);
// Result will be true if success otherwise false


// MANUALLY CREATE INSTANCE OF ACCOUNT RESULT

// if you already know the id of page, we can create an instance of FbPage by using AccountResult class
$accountResult = new AccountResult($pageId, $pageAccessToken, $facebookSdkInstance);
$fbPage = $accountResult->fetchObject();
// get list of tab
$listOfTab = $fbPage->getTabs();
// add tab to the page
$result = $fbPage->addTab($appId);