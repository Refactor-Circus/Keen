{{--
    The History section Keen adds to other packages' screens: the screen
    record's history on a detail screen, the package's otherwise. `source`
    and `subject` come from HistoryExtensions' resolver.
--}}
<x-keen::audit-trail :source="$source" :subject="$subject" />
