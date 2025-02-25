// / <reference types="Cypress" />
import CustomerPageObject from '../../../../support/pages/module/sw-customer.page-object';

describe('Flow builder: change customer status testing', () => {
    beforeEach(() => {
        cy.createCustomerFixture();
    });

    it('@settings: change customer status flow', { tags: ['pa-business-ops'] }, () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'POST',
        }).as('saveData');

        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.contains('.smart-bar__header h2', 'New flow');

        // Fill all fields
        cy.get('#sw-field--flow-name').type('Change customer status');
        cy.get('#sw-field--flow-priority').type('10');
        cy.get('.sw-flow-detail-general__general-active .sw-field--switch__input').click();

        cy.get('.sw-flow-detail__tab-flow').click();
        cy.get('.sw-flow-trigger__input-field').type('checkout customer logi');
        cy.get('.sw-flow-trigger__search-result').should('be.visible');
        cy.get('.sw-flow-trigger__search-result').eq(1).click();

        cy.get('.sw-flow-sequence-selector').should('be.visible');
        cy.get('.sw-flow-sequence-selector__add-action').click();

        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Assign account status', '.sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-change-customer-status-modal').should('be.visible');

        cy.get('.sw-flow-change-customer-status-modal__type-select')
            .typeSingleSelect('Inactive', '.sw-flow-change-customer-status-modal__type-select');

        cy.get('.sw-flow-change-customer-status-modal__save-button').click();
        cy.get('.sw-flow-change-customer-status-modal').should('not.exist');

        // Save
        cy.get('.sw-flow-detail__save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.visit('/account/login');

        // Login
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // Clear Storefront cookie
        cy.clearCookies();

        const page = new CustomerPageObject();

        cy.authenticate().then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/customer/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
            cy.contains(`${page.elements.dataGridRow}--0`, 'Eroni');
        });

        cy.clickContextMenuItem(
            '.sw-customer-list__view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-description-list dd').should('be.visible').eq(2).contains('Inactive');
    });
});
