import { n as e } from "./entries-ofT4k6gi.js";
import { AtriumIntegrationScreen as t, AtriumNoAccess as n, atriumStatus as r, integrationData as i, useAtriumIntegration as a, useHead as o } from "@refactor-circus/atrium";
import { Fragment as s, computed as c, createBlock as l, createCommentVNode as u, createElementBlock as d, createElementVNode as f, createVNode as p, defineComponent as m, openBlock as h, ref as g, renderList as _, shallowRef as v, toDisplayString as y, unref as b, watch as x, withCtx as S } from "vue";
import { UAlert as C, UButton as w, UCard as T, UDashboardNavbar as E, UDashboardPanel as D, USkeleton as O, UTable as k } from "@refactor-circus/atrium/ui";
import { useI18n as A } from "vue-i18n";
import { useRoute as j } from "vue-router";
// src/components/KeenChanges.vue?vue&type=script&setup=true&lang.ts
var M = {
	key: 0,
	class: "text-sm text-muted",
	"data-testid": "keen-no-changes"
}, N = { "data-testid": "keen-change-before" }, P = { "data-testid": "keen-change-after" }, F = /* @__PURE__ */ m({
	__name: "KeenChanges",
	props: { changes: {} },
	setup(t) {
		let n = t, { t: r } = A(), i = c(() => e(n.changes)), a = c(() => [
			{
				accessorKey: "field",
				header: r("keen.changes.field")
			},
			{
				accessorKey: "before",
				header: r("keen.changes.before"),
				meta: { class: { td: "break-all whitespace-normal" } }
			},
			{
				accessorKey: "after",
				header: r("keen.changes.after"),
				meta: { class: { td: "break-all whitespace-normal" } }
			}
		]);
		return (e, t) => {
			let n = k;
			return b(i).length === 0 ? (h(), d("p", M, y(b(r)("keen.changes.none")), 1)) : (h(), l(n, {
				key: 1,
				data: b(i),
				columns: b(a),
				"data-testid": "keen-changes"
			}, {
				"field-cell": S(({ row: e }) => [f("code", null, y(e.original.field), 1)]),
				"before-cell": S(({ row: e }) => [f("span", N, y(e.original.before), 1)]),
				"after-cell": S(({ row: e }) => [f("span", P, y(e.original.after), 1)]),
				_: 1
			}, 8, ["data", "columns"]));
		};
	}
}), I = {
	key: 4,
	class: "grid gap-4 lg:grid-cols-2",
	"data-testid": "keen-entry"
}, L = { class: "font-medium" }, R = {
	key: 0,
	class: "text-sm text-muted"
}, z = {
	class: "grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm",
	"data-testid": "keen-entry-details"
}, B = { class: "text-muted" }, V = ["data-testid"], H = { class: "font-medium" }, U = {
	key: 0,
	class: "mt-4 overflow-x-auto text-xs",
	"data-testid": "keen-entry-context"
}, W = /* @__PURE__ */ m({
	__name: "[entry]",
	setup(e) {
		let m = j(), { t: k, locale: M } = A(), { client: N } = a("keen"), P = c(() => String(m.params.entry)), W = v(null), G = v(null), K = g(!0), q = c(() => r(G.value));
		async function J() {
			if (N) {
				K.value = !0, G.value = null;
				try {
					W.value = i(await N.GET("/entries/{entry}", { params: { path: { entry: P.value } } })).data;
				} catch (e) {
					W.value = null, G.value = e;
				} finally {
					K.value = !1;
				}
			}
		}
		x(P, () => void J(), { immediate: !0 });
		let Y = c(() => W.value?.action ?? k("keen.entry.title")), X = c(() => W.value ? new Date(W.value.created_at).toLocaleString(M.value) : null), Z = c(() => W.value !== null && ("ip" in W.value || "user_agent" in W.value)), Q = c(() => W.value && Object.keys(W.value.context ?? {}).length > 0 ? JSON.stringify(W.value.context, null, 2) : null), $ = c(() => {
			let e = W.value;
			if (!e) return [];
			let t = [
				{
					key: "source",
					term: k("keen.entry.source"),
					text: e.source === "app" ? k("keen.filters.source_app") : e.source
				},
				{
					key: "subject",
					term: k("keen.entry.subject"),
					text: [
						e.subject?.label ?? k("keen.entries.none"),
						e.subject?.type,
						e.subject?.id
					].filter(Boolean).join(" ")
				},
				{
					key: "actor",
					term: k("keen.entry.actor"),
					text: e.actor?.label ?? k("keen.entries.system")
				}
			];
			return e.scope && t.push({
				key: "scope",
				term: k("keen.entry.scope"),
				text: [e.scope.type, e.scope.id].filter(Boolean).join(" ")
			}), t.push({
				key: "surface",
				term: k("keen.entry.surface"),
				text: e.surface
			}), Z.value && t.push({
				key: "ip",
				term: k("keen.entry.ip"),
				text: e.ip ?? k("keen.entries.none")
			}, {
				key: "user_agent",
				term: k("keen.entry.user_agent"),
				text: e.user_agent ?? k("keen.entries.none")
			}), t.push({
				key: "hash",
				term: k("keen.entry.hash"),
				text: e.hash
			}), t;
		});
		return o({ title: () => Y.value }), (e, r) => {
			let i = w, a = E, o = n, c = C, m = O, g = T, v = t, x = D;
			return h(), l(x, null, {
				header: S(() => [p(a, { title: b(Y) }, {
					leading: S(() => [p(i, {
						to: "/keen/entries",
						icon: "i-lucide-arrow-left",
						color: "neutral",
						variant: "ghost",
						"aria-label": b(k)("keen.entry.back")
					}, null, 8, ["aria-label"])]),
					_: 1
				}, 8, ["title"])]),
				body: S(() => [p(v, {
					"screen-key": "keen.entry",
					record: b(P)
				}, {
					default: S(() => [b(q) === 403 ? (h(), l(o, { key: 0 })) : b(q) === 404 ? (h(), l(c, {
						key: 1,
						color: "neutral",
						variant: "subtle",
						icon: "i-lucide-search-x",
						title: b(k)("keen.entry.not_found"),
						description: b(k)("keen.entry.not_found_description"),
						"data-testid": "keen-entry-not-found"
					}, null, 8, ["title", "description"])) : b(G) ? (h(), l(c, {
						key: 2,
						color: "error",
						title: b(k)("keen.entry.load_failed"),
						"data-testid": "keen-entry-failed"
					}, null, 8, ["title"])) : b(K) && !b(W) ? (h(), l(m, {
						key: 3,
						class: "h-40"
					})) : b(W) ? (h(), d("div", I, [p(g, null, {
						header: S(() => [f("h2", L, y(b(k)("keen.entry.details")), 1), b(X) ? (h(), d("p", R, y(b(X)), 1)) : u("", !0)]),
						default: S(() => [f("dl", z, [(h(!0), d(s, null, _(b($), (e) => (h(), d(s, { key: e.key }, [f("dt", B, y(e.term), 1), f("dd", {
							class: "break-all",
							"data-testid": `keen-entry-${e.key}`
						}, y(e.text), 9, V)], 64))), 128))])]),
						_: 1
					}), p(g, null, {
						header: S(() => [f("h2", H, y(b(k)("keen.entry.changes")), 1)]),
						default: S(() => [p(F, { changes: b(W).changes }, null, 8, ["changes"]), b(Q) ? (h(), d("pre", U, y(b(Q)), 1)) : u("", !0)]),
						_: 1
					})])) : u("", !0)]),
					_: 1
				}, 8, ["record"])]),
				_: 1
			});
		};
	}
});
export { W as default };
