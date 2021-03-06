const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'folder', 'folder-dissolve', 'dissolve'],
    before: (browser, done) => {
        global.MediaFixtureService.setFolderFixture().then(() => {
            done();
        });
    },
    '@disabled': !global.flags.isActive('next1207'),
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
    },
    'verify creation of the new folder and navigate': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridItem}--0 .sw-media-base-item__preview-container`)
            .clickContextMenuItem(page.elements.showMediaAction, page.elements.contextMenuButton)
            .waitForElementVisible('.icon--folder-thumbnail-back')
            .waitForElementVisible('.smart-bar__header')
            .expect.element('.smart-bar__header').to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'upload image to folder and verify placement in folder': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);

        browser
            .assert.containsText(page.elements.mediaNameLabel, 'sw-login-background.png')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'navigate back to root folder': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.icon--folder-breadcrumbs-back-to-root')
            .click('.icon--folder-breadcrumbs-back-to-root')
            .waitForElementNotPresent(page.elements.previewItem);
    },
    'dissolve folder': (browser) => {
        const page = mediaPage(browser);

        browser
            .clickContextMenuItem('.sw-media-context-item__dissolve-folder-action', page.elements.contextMenuButton, `${page.elements.gridItem}--0`)
            .waitForElementVisible(page.elements.modal)
            .assert.containsText(`${page.elements.modal}__body`, `Are you sure you want to dissolve "${global.MediaFixtureService.mediaFolderFixture.name}" ?`)
            .waitForElementVisible('.sw-media-modal-folder-dissolve__confirm')
            .click('.sw-media-modal-folder-dissolve__confirm')
            .checkNotification(`Folder "${global.MediaFixtureService.mediaFolderFixture.name}" has been dissolved successfully`, false)
            .click(page.elements.alertClose)
            .expect.element('.sw-alert__message').to.have.text.not.equals('Folder ${global.MediaFixtureService.mediaFolderFixture.name} has been dissolved successfully').before(500);

        browser.checkNotification('Folders have been dissolved successfully');
    },
    'verify if folder was removed and images persisted': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementPresent(page.elements.previewItem)
            .waitForElementVisible(page.elements.mediaNameLabel)
            .waitForElementNotPresent(page.elements.folderNameLabel)
            .expect.element(page.elements.mediaNameLabel).to.have.text.that.equals('sw-login-background.png');
    },
    after: (browser) => {
        browser.end();
    }
};
