{{--
    The Activity tab's panel: what was recorded in the screen's record when
    the tab is scoped, or the record's own history. `source`, `scope` and
    `subject` come from HistoryExtensions' resolver.
--}}
<x-keen::audit-trail :source="$source" :scope="$scope" :subject="$subject" />
