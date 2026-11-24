import { n as e } from "./entries-ofT4k6gi.js";
import { AtriumDetails as t, AtriumIntegrationScreen as n, AtriumNoAccess as r, atriumStatus as i, integrationData as a, useAtriumIntegration as o, useHead as s } from "@refactor-circus/atrium";
import { computed as c, createBlock as l, createCommentVNode as u, createElementBlock as d, createElementVNode as f, createVNode as p, defineComponent as m, openBlock as h, ref as g, shallowRef as _, toDisplayString as v, unref as y, watch as b, withCtx as x } from "vue";
import { UAlert as S, UButton as C, UCard as w, UDashboardNavbar as T, UDashboardPanel as E, USkeleton as D, UTable as O } from "@refactor-circus/atrium/ui";
import { useI18n as k } from "vue-i18n";
import { useRoute as A } from "vue-router";
// src/components/KeenChanges.vue?vue&type=script&setup=true&lang.ts
var j = {
	key: 0,
	class: "text-sm text-muted",
	"data-testid": "keen-no-changes"
}, M = { "data-testid": "keen-change-before" }, N = { "data-testid": "keen-change-after" }, P = /* @__PURE__ */ m({
	__name: "KeenChanges",
	props: { changes: {} },
	setup(t) {
		let n = t, { t: r } = k(), i = c(() => e(n.changes)), a = c(() => [
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
			let n = O;
			return y(i).length === 0 ? (h(), d("p", j, v(y(r)("keen.changes.none")), 1)) : (h(), l(n, {
				key: 1,
				data: y(i),
				columns: y(a),
				"data-testid": "keen-changes"
			}, {
				"field-cell": x(({ row: e }) => [f("code", null, v(e.original.field), 1)]),
				"before-cell": x(({ row: e }) => [f("span", M, v(e.original.before), 1)]),
				"after-cell": x(({ row: e }) => [f("span", N, v(e.original.after), 1)]),
				_: 1
			}, 8, ["data", "columns"]));
		};
	}
}), F = {
	key: 4,
	class: "grid gap-4 lg:grid-cols-2",
	"data-testid": "keen-entry"
}, I = { class: "flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-x-4 gap-y-1" }, L = {
	class: "font-medium",
	"data-testid": "keen-entry-details-title"
}, R = {
	key: 0,
	class: "text-sm text-muted tabular-nums",
	"data-testid": "keen-entry-created-at"
}, z = { class: "font-medium" }, B = {
	key: 0,
	class: "mt-4 overflow-x-auto text-xs",
	"data-testid": "keen-entry-context"
}, V = /* @__PURE__ */ m({
	__name: "[entry]",
	setup(e) {
		let m = A(), { t: O, locale: j } = k(), { client: M } = o("keen"), N = c(() => String(m.params.entry)), V = _(null), H = _(null), U = g(!0), W = c(() => i(H.value));
		async function G() {
			if (M) {
				U.value = !0, H.value = null;
				try {
					V.value = a(await M.GET("/entries/{entry}", { params: { path: { entry: N.value } } })).data;
				} catch (e) {
					V.value = null, H.value = e;
				} finally {
					U.value = !1;
				}
			}
		}
		b(N, () => void G(), { immediate: !0 });
		let K = c(() => V.value?.action ?? O("keen.entry.title")), q = c(() => V.value ? new Date(V.value.created_at).toLocaleString(j.value) : null), J = c(() => V.value !== null && ("ip" in V.value || "user_agent" in V.value)), Y = c(() => V.value && Object.keys(V.value.context ?? {}).length > 0 ? JSON.stringify(V.value.context, null, 2) : null), X = c(() => {
			let e = V.value;
			if (!e) return [];
			let t = [
				{
					key: "source",
					label: O("keen.entry.source"),
					value: e.source === "app" ? O("keen.filters.source_app") : e.source
				},
				{
					key: "subject",
					label: O("keen.entry.subject"),
					value: [
						e.subject?.label ?? O("keen.entries.none"),
						e.subject?.type,
						e.subject?.id
					].filter(Boolean).join(" ")
				},
				{
					key: "actor",
					label: O("keen.entry.actor"),
					value: e.actor?.label ?? O("keen.entries.system")
				}
			];
			return e.scope && t.push({
				key: "scope",
				label: O("keen.entry.scope"),
				value: [e.scope.type, e.scope.id].filter(Boolean).join(" ")
			}), t.push({
				key: "surface",
				label: O("keen.entry.surface"),
				value: e.surface
			}), J.value && t.push({
				key: "ip",
				label: O("keen.entry.ip"),
				value: e.ip ?? O("keen.entries.none"),
				mono: !0
			}, {
				key: "user_agent",
				label: O("keen.entry.user_agent"),
				value: e.user_agent ?? O("keen.entries.none")
			}), t.push({
				key: "hash",
				label: O("keen.entry.hash"),
				value: e.hash,
				mono: !0
			}), t.map((e) => ({
				...e,
				testid: `keen-entry-${e.key}`
			}));
		});
		return s({ title: () => K.value }), (e, i) => {
			let a = C, o = T, s = r, c = S, m = D, g = t, _ = w, b = n, k = E;
			return h(), l(k, null, {
				header: x(() => [p(o, { title: y(K) }, {
					leading: x(() => [p(a, {
						to: "/keen/entries",
						icon: "i-lucide-arrow-left",
						color: "neutral",
						variant: "ghost",
						"aria-label": y(O)("keen.entry.back")
					}, null, 8, ["aria-label"])]),
					_: 1
				}, 8, ["title"])]),
				body: x(() => [p(b, {
					"screen-key": "keen.entry",
					record: y(N)
				}, {
					default: x(() => [y(W) === 403 ? (h(), l(s, { key: 0 })) : y(W) === 404 ? (h(), l(c, {
						key: 1,
						color: "neutral",
						variant: "subtle",
						icon: "i-lucide-search-x",
						title: y(O)("keen.entry.not_found"),
						description: y(O)("keen.entry.not_found_description"),
						"data-testid": "keen-entry-not-found"
					}, null, 8, ["title", "description"])) : y(H) ? (h(), l(c, {
						key: 2,
						color: "error",
						title: y(O)("keen.entry.load_failed"),
						"data-testid": "keen-entry-failed"
					}, null, 8, ["title"])) : y(U) && !y(V) ? (h(), l(m, {
						key: 3,
						class: "h-40"
					})) : y(V) ? (h(), d("div", F, [p(_, null, {
						header: x(() => [f("div", I, [f("h2", L, v(y(O)("keen.entry.details")), 1), y(q) ? (h(), d("p", R, v(y(q)), 1)) : u("", !0)])]),
						default: x(() => [p(g, {
							items: y(X),
							"data-testid": "keen-entry-details"
						}, null, 8, ["items"])]),
						_: 1
					}), p(_, null, {
						header: x(() => [f("h2", z, v(y(O)("keen.entry.changes")), 1)]),
						default: x(() => [p(P, { changes: y(V).changes }, null, 8, ["changes"]), y(Y) ? (h(), d("pre", B, v(y(Y)), 1)) : u("", !0)]),
						_: 1
					})])) : u("", !0)]),
					_: 1
				}, 8, ["record"])]),
				_: 1
			});
		};
	}
});
export { V as default };
