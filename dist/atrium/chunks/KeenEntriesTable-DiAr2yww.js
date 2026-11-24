import "./entries-ofT4k6gi.js";
import { computed as e, createBlock as t, createCommentVNode as n, createElementVNode as r, createTextVNode as i, createVNode as a, defineComponent as o, openBlock as s, toDisplayString as c, unref as l, withCtx as u } from "vue";
import { UBadge as d, UEmpty as f, ULink as p, UTable as m } from "@refactor-circus/atrium/ui";
import { useI18n as h } from "vue-i18n";
// src/components/KeenEntriesTable.vue
var g = /* @__PURE__ */ o({
	__name: "KeenEntriesTable",
	props: {
		entries: {},
		withoutSubject: {
			type: Boolean,
			default: !1
		}
	},
	setup(o) {
		let g = o, { t: _, locale: v } = h();
		function y(e) {
			let t = new Date(e.created_at);
			return Number.isNaN(t.getTime()) ? e.created_at : t.toLocaleString(v.value);
		}
		let b = e(() => g.entries.map((e) => ({
			entry: e,
			when: y(e),
			subject: e.subject?.label ?? _("keen.entries.none"),
			actor: e.actor?.label ?? _("keen.entries.system")
		}))), x = e(() => [
			{
				id: "when",
				header: _("keen.entries.when"),
				meta: { class: { td: "whitespace-nowrap" } }
			},
			{
				id: "action",
				header: _("keen.entries.action")
			},
			...g.withoutSubject ? [] : [{
				accessorKey: "subject",
				header: _("keen.entries.subject")
			}],
			{
				accessorKey: "actor",
				header: _("keen.entries.actor")
			},
			{
				id: "surface",
				header: _("keen.entries.surface")
			}
		]);
		return (e, o) => {
			let h = f, g = p, v = d, y = m;
			return l(b).length === 0 ? (s(), t(h, {
				key: 0,
				icon: "i-lucide-scroll-text",
				title: l(_)("keen.entries.empty"),
				size: "xs",
				variant: "naked",
				ui: { root: "py-6" },
				role: "status",
				"data-testid": "keen-entries-empty"
			}, null, 8, ["title"])) : (s(), t(y, {
				key: 1,
				data: l(b),
				columns: l(x),
				"data-testid": "keen-entries"
			}, {
				"when-cell": u(({ row: e }) => [a(g, {
					to: `/keen/entries/${e.original.entry.id}`,
					title: e.original.entry.created_at,
					class: "hover:underline"
				}, {
					default: u(() => [i(c(e.original.when), 1)]),
					_: 2
				}, 1032, ["to", "title"])]),
				"action-cell": u(({ row: e }) => [r("code", null, c(e.original.entry.action), 1), e.original.entry.source === l("app") ? (s(), t(v, {
					key: 0,
					color: "info",
					variant: "subtle",
					size: "sm",
					class: "ms-2"
				}, {
					default: u(() => [i(c(l(_)("keen.entries.app_badge")), 1)]),
					_: 1
				})) : n("", !0)]),
				"surface-cell": u(({ row: e }) => [a(v, {
					color: "neutral",
					variant: "subtle",
					size: "sm"
				}, {
					default: u(() => [i(c(e.original.entry.surface), 1)]),
					_: 2
				}, 1024)]),
				_: 1
			}, 8, ["data", "columns"]));
		};
	}
});
export { g as t };
