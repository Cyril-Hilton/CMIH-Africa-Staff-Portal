import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isPaginationDestination } from '../../resources/js/navigation-link-policy.js';
const current = 'https://portal.cmih.africa/dashboard?exec_view=projects';
test('same-page pagination keeps its collection behaviour', () => {
    assert.equal(isPaginationDestination('/dashboard?exec_page=2', current), true);
    assert.equal(isPaginationDestination('/dashboard?page=2', current), true);
});
test('source links and back links to different routes navigate normally even with page parameters', () => {
    assert.equal(isPaginationDestination('/portal/tasks/2382?page=2', current), false);
    assert.equal(isPaginationDestination('/dashboard?exec_page=2', 'https://portal.cmih.africa/portal/tasks/2382'), false);
    assert.equal(isPaginationDestination('https://example.com/dashboard?page=2', current), false);
    assert.equal(isPaginationDestination('/portal/tasks/2382', current, true), false);
});
test('plain source and stage anchors are not pagination', () => {
    assert.equal(isPaginationDestination('/portal/tasks/2382', current), false);
    assert.equal(isPaginationDestination('#flow-1-creative', current), false);
});
