# Test matrix (PLAN §7)

Every test PLAN §7 asks for, and the test methods that cover it. `tests/Architecture/TestMatrixTest.php` fails if an
item has no reference or a referenced method does not exist. Format per line:
`- **ID** claim: \`tests/…Test.php::method\`, …`

## Library

- **L1** ScopedCapabilityProvider: exact tools and resources for every place × role (synthetic workflow): `tests/Unit/Lib/WorkflowMcp/ScopedCapabilityProviderTest.php::testVisibilityForEveryPlaceAndRole`, `tests/Unit/Lib/WorkflowMcp/ScopedCapabilityProviderTest.php::testResourceVisibilityForEveryPlaceAndRole`
- **L2** A guard denial hides a transition tool: `tests/Unit/Lib/WorkflowMcp/ScopedCapabilityProviderTest.php::testGuardHidesTransitionTool`
- **L3** A role mismatch hides a transition tool (data rows, e.g. draft × editor): `tests/Unit/Lib/WorkflowMcp/ScopedCapabilityProviderTest.php::testVisibilityForEveryPlaceAndRole`
- **L4** A custom RoleResolverInterface is respected: `tests/Unit/Lib/WorkflowMcp/ScopedCapabilityProviderTest.php::testCustomRoleResolverFromWorkflowMetadata`
- **L5** SchemaFactory: types, enums, nullable, Choice → schema; Range, Length, Email, Url stay server-side (decisions/015): `tests/Unit/Lib/WorkflowMcp/SchemaFactoryTest.php::testInputSchemaFromDto`, `tests/Unit/Lib/WorkflowMcp/SchemaFactoryTest.php::testChoiceBecomesAnEnumWhileEmailAndUrlStayPlainStrings`, `tests/Unit/Lib/WorkflowMcp/InputMapperTest.php::testChoiceEmailAndUrlAreCheckedServerSide`, `tests/Unit/Lib/WorkflowMcp/InputMapperTest.php::testInvalidArgumentsGiveFieldMessages`
- **L6** SchemaFactory: unsupported constructs fail loudly: `tests/Unit/Lib/WorkflowMcp/SchemaFactoryTest.php::testUnsupportedPropertyTypesFailLoudly`
- **L7** MRTR: first call returns input_required with no side effects: `tests/Unit/Lib/WorkflowMcp/ApprovalFlowTest.php::testFirstCallAsksWithoutSideEffects`
- **L8** MRTR: a valid retry applies once: `tests/Unit/Lib/WorkflowMcp/ApprovalFlowTest.php::testAcceptedRetryAppliesTheAmendedInputOnce`
- **L9** MRTR: a duplicate retry is idempotent: `tests/Unit/Lib/WorkflowMcp/ApprovalFlowTest.php::testDuplicateRetryReplaysTheStoredResult`, `tests/Integration/Lib/DoctrineProcessedInputRequestsTest.php::testAppliesOncePerNonceAndReplaysFromTheDatabase`
- **L10** MRTR: tampered state is rejected: `tests/Unit/Lib/WorkflowMcp/ApprovalFlowTest.php::testTamperedStateIsAnInvalidParamsErrorFromTheSdk`, `tests/Unit/Lib/HumanInput/RequestBinderTest.php::testTamperedStateIsRejectedBySdkCodec`
- **L11** MRTR: expired state is rejected: `tests/Unit/Lib/WorkflowMcp/ApprovalFlowTest.php::testExpiredStateIsRejected`, `tests/Unit/Lib/HumanInput/RequestBinderTest.php::testRejectsOwnExpiry`
- **L12** MRTR: a wrong caller is rejected: `tests/Unit/Lib/WorkflowMcp/ApprovalFlowTest.php::testRetryByAnotherCallerIsRejected`
- **L13** MRTR: mismatched arguments are rejected: `tests/Unit/Lib/WorkflowMcp/ApprovalFlowTest.php::testRetryWithChangedArgumentsIsRejected`, `tests/Unit/Lib/WorkflowMcp/ApprovalFlowTest.php::testRetryOnAnotherSubjectIsRejected`
- **L14** MRTR: decline and cancel are handled: `tests/Unit/Lib/WorkflowMcp/ApprovalFlowTest.php::testDeclineChangesNothing`, `tests/Unit/Lib/WorkflowMcp/ApprovalFlowTest.php::testCancelChangesNothing`, `tests/Unit/Lib/WorkflowMcp/ApprovalFlowTest.php::testDeclineWithAReasonIsReportedOnceAndAppliesNothing`
- **L15** Client: input_required → PendingInput + notification: `tests/Unit/Lib/HumanInput/DeferredInputFlowTest.php::testDeferredCallParksAPendingInputNotifiesAndTellsTheAgentToStop`
- **L16** Client: answer → retry with the original caller's token → RunNextActor: `tests/Unit/Lib/HumanInput/DeferredInputFlowTest.php::testResumeRetriesWithTheOriginalCallersTokenAppliesOnceAndAnnounces`, `tests/Integration/RunNextActorOnResolvedTest.php::testResolvedPendingInputQueuesRunNextActor`
- **L17** ElicitMissingInputPolicy asks for exactly the missing fields: `tests/Unit/Lib/WorkflowMcp/ElicitMissingInputPolicyTest.php::testHumanBackedCallerIsAskedForExactlyTheMissingFields`
- **L18** The client toolbox forwards a call to an unlisted tool to the server: `tests/Unit/Lib/WorkflowMcp/McpClientToolboxTest.php::testHiddenToolReachesTheServerAndComesBackAsText`
- **L19** ActorSelector ignores read-only tools: `tests/Unit/Lib/WorkflowMcp/ActorSelectorTest.php::testSelectsRolesWithAnActingTool`
- **L20** A paused subject gets no actor (the runner skips it): `tests/Integration/Agent/NextActorRunnerTest.php::testOpenHumanInputPausesTheGame`
- **L21** ActorSelector per-caller form picks the right callers: `tests/Unit/Lib/WorkflowMcp/ActorSelectorTest.php::testPicksTheCallersWithAnActingTool`
- **L22** A second run on a locked subject is skipped: `tests/Integration/Agent/NextActorRunnerTest.php::testHeldLockSkipsTheStep`
- **L23** /inbox without login redirects: `tests/Integration/Inbox/InboxTest.php::testInboxRequiresLogin`
- **L24** A signed item URL works without login: `tests/Integration/Inbox/InboxTest.php::testSignedLinkOpensTheItemAsAChoiceWithoutLogin`
- **L25** An expired signed URL does not: `tests/Integration/Inbox/InboxTest.php::testExpiredLinkIsGone`
- **L26** A Telegram callback from a foreign chat is ignored: `tests/Integration/Telegram/TelegramHumanSeatTest.php::testButtonPressResumesTheVote`
- **L27** Nothing in lib/ references App\, Werewolf, DocReview or refunds: `tests/Architecture/LibraryBoundariesTest.php::testLibraryDoesNotReference`
- **L28** Nothing in lib/HumanInput references Leash\WorkflowMcp\: `tests/Architecture/LibraryBoundariesTest.php::testLibraryDoesNotReference`

## Werewolf

- **W1** Rules engine suite: `tests/Unit/Werewolf/Rules/ScriptedGamesTest.php`, `tests/Architecture/WerewolfRulesPurityTest.php::testRulesDoNotReference`, `tests/Unit/Werewolf/Rules/GameRulesTest.php`
- **W2** A villager never sees wolf-den or ww_choose_victim: `tests/Integration/Werewolf/WerewolfVisibilityTest.php::testNightOneEachSeatSeesOnlyItsOwnToolsAndResources`, `tests/Integration/Werewolf/WerewolfMcpGameTest.php::testScriptedSeatsPlayAFullGame`
- **W3** A dead seat sees only public-log: `tests/Integration/Werewolf/WerewolfVisibilityTest.php::testADeadSeatSeesOnlyThePublicLog`
- **W4** Moderator transitions are never visible to seats: `tests/Integration/Werewolf/WerewolfVisibilityTest.php::testModeratorTransitionsAreVisibleToTheModeratorOnly`, `tests/Integration/Dashboard/SeatPanelMatchesServerTest.php::testPanelEqualsToolsAndResourcesListedOverMcpInEveryPhase`
- **W5** A full scripted game over HTTP MCP: `tests/Integration/Werewolf/WerewolfMcpGameTest.php::testScriptedSeatsPlayAFullGame`
- **W6** Same seed → same game: `tests/Integration/Werewolf/WerewolfMcpGameTest.php::testSameSeedGivesTheSameGame`
- **W7** Human seat vote via input_required: `tests/Integration/Werewolf/HumanSeatTest.php::testHumanVoteIsAskedAnsweredAndTheGameContinues`
- **W8** Replay: make werewolf completes offline: `tests/Integration/Agent/WerewolfReplayTest.php::testCommittedCassetteReplaysToTheEnd`, `tests/Integration/Demo/DemoPrepareTest.php::testWholeDemoReplaysWithoutAModel`

## Doc Review

- **D1** DocReviewFlowTest (M6 acceptance): `tests/Integration/DocReview/DocReviewFlowTest.php::testPublishAsksFirstThenAnEditedHeadlineIsPublishedOnce`, `tests/Integration/DocReview/DocReviewFlowTest.php::testADeclineWithANoteReachesTheAuthorThroughSendBack`, `tests/Integration/DocReview/DocReviewFlowTest.php::testAuthorNeverSeesTheRubricOrReviewerTools`
- **D2** ActorSelector hands off author → reviewer → editor (human, MRTR) → author: `tests/Integration/DocReview/DocReviewHandOffTest.php::testTheWorkflowPicksTheNextAgent`, `tests/Integration/DocReview/DocReviewHandOffTest.php::testDeclinedDocumentGoesBackToTheAuthor`, `tests/Integration/DocReview/DocReviewAgentsTest.php::testAgentsWorkUntilTheEditorThenTheDeclineNoteReachesTheAuthor`
- **D3** The rubric is visible only to the reviewer in in_review: `tests/Integration/DocReview/DocReviewHandOffTest.php::testRubricIsVisibleOnlyToTheReviewerInReview`
