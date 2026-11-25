import { t as e } from "./KeenEntriesTable-BrbCchJu.js";
import { i as t, r as n, t as r } from "./entries-ofT4k6gi.js";
import { AtriumNoAccess as i, AtriumSelect as ee, atriumStatus as a, integrationData as o, useAtriumIntegration as s } from "@refactor-circus/atrium";
import { Fragment as c, computed as l, createBlock as u, createCommentVNode as d, createElementBlock as f, createElementVNode as p, createTextVNode as m, createVNode as h, defineComponent as g, normalizeClass as te, openBlock as _, reactive as v, ref as y, renderList as b, shallowRef as x, toDisplayString as S, unref as C, watch as w, withCtx as T } from "vue";
import { UAlert as ne, UButton as re, UCard as ie, UForm as ae, UFormField as E, UInput as D, USkeleton as O } from "@refactor-circus/atrium/ui";
import { useI18n as k } from "vue-i18n";
import { useRoute as A, useRouter as j } from "vue-router";
// src/components/KeenEntriesBrowser.vue?vue&type=script&setup=true&lang.ts
var M = {
	key: 1,
	class: "space-y-4"
}, N = { class: "mt-field-label flex gap-2" }, P = ["aria-label"], F = /* @__PURE__ */ g({
	__name: "KeenEntriesBrowser",
	props: {
		source: {},
		sources: {}
	},
	setup(g, { expose: F }) {
		let I = g, L = A(), R = j(), { t: z } = k(), { client: B } = s("keen"), V = {
			source: z("keen.filters.source"),
			action: z("keen.filters.action"),
			subject_type: z("keen.filters.subject_type"),
			subject_id: z("keen.filters.subject_id"),
			actor_type: z("keen.filters.actor_type"),
			actor_id: z("keen.filters.actor_id"),
			since: z("keen.filters.since"),
			until: z("keen.filters.until")
		}, H = r.filter((e) => e !== "source"), U = l(() => {
			let e = n(L.query);
			return I.source && (e.source = I.source), e;
		}), W = v(Object.fromEntries(r.map((e) => [e, ""])));
		w(U, (e) => {
			for (let t of r) W[t] = e[t] ?? "";
		}, { immediate: !0 });
		let oe = l(() => [...I.sources ?? [], {
			value: "app",
			label: z("keen.filters.source_app")
		}]), G = x([]), K = x(null), q = x(null), J = y(!1), Y = l(() => a(q.value) === 403), X = l(() => t(q.value)), se = l(() => q.value !== null && !Y.value && Object.keys(X.value).length === 0);
		async function Z() {
			if (B) {
				J.value = !0, q.value = null;
				try {
					let e = o(await B.GET("/entries", { params: { query: U.value } }));
					G.value = e.data, K.value = e.meta;
				} catch (e) {
					q.value = e, G.value = [], K.value = null;
				} finally {
					J.value = !1;
				}
			}
		}
		w(() => JSON.stringify(U.value), () => void Z(), { immediate: !0 });
		function Q() {
			let e = { ...L.query };
			for (let t of [...r, "cursor"]) delete e[t];
			return e;
		}
		function ce() {
			let e = {};
			for (let t of r) W[t] !== "" && !(t === "source" && I.source) && (e[t] = W[t]);
			R.replace({ query: {
				...Q(),
				...e
			} });
		}
		function le() {
			R.replace({ query: Q() });
		}
		function $(e) {
			let t = { ...L.query };
			delete t.cursor, R.replace({ query: e ? {
				...t,
				cursor: e
			} : t });
		}
		return F({ reload: Z }), (t, n) => {
			let r = i, a = ee, o = E, s = D, l = re, v = ae, y = ie, x = ne, w = O;
			return C(Y) ? (_(), u(r, { key: 0 })) : (_(), f("div", M, [
				h(y, null, {
					default: T(() => [h(v, {
						state: C(W),
						class: "flex flex-wrap items-start gap-3",
						"data-testid": "keen-filters",
						onSubmit: ce
					}, {
						default: T(() => [
							g.source ? d("", !0) : (_(), u(o, {
								key: 0,
								label: V.source,
								name: "source",
								error: C(X).source?.[0],
								class: "w-44"
							}, {
								default: T(() => [h(a, {
									modelValue: C(W).source,
									"onUpdate:modelValue": n[0] ||= (e) => C(W).source = e,
									items: C(oe),
									blank: C(z)("keen.filters.all_sources"),
									class: "w-full",
									"data-testid": "keen-filter-source"
								}, null, 8, [
									"modelValue",
									"items",
									"blank"
								])]),
								_: 1
							}, 8, ["label", "error"])),
							(_(!0), f(c, null, b(C(H), (e) => (_(), u(o, {
								key: e,
								label: V[e],
								name: e,
								hint: e === "action" ? C(z)("keen.filters.action_hint") : void 0,
								error: C(X)[e]?.[0],
								class: te(e === "action" ? "w-56" : "w-44")
							}, {
								default: T(() => [h(s, {
									modelValue: C(W)[e],
									"onUpdate:modelValue": (t) => C(W)[e] = t,
									type: e === "since" || e === "until" ? "date" : "text",
									class: "w-full",
									"data-testid": `keen-filter-${e}`
								}, null, 8, [
									"modelValue",
									"onUpdate:modelValue",
									"type",
									"data-testid"
								])]),
								_: 2
							}, 1032, [
								"label",
								"name",
								"hint",
								"error",
								"class"
							]))), 128)),
							p("div", N, [h(l, {
								type: "submit",
								icon: "i-lucide-funnel",
								"data-testid": "keen-filter-apply"
							}, {
								default: T(() => [m(S(C(z)("keen.filters.apply")), 1)]),
								_: 1
							}), h(l, {
								type: "button",
								icon: "i-lucide-x",
								color: "neutral",
								variant: "ghost",
								"data-testid": "keen-filter-clear",
								onClick: le
							}, {
								default: T(() => [m(S(C(z)("keen.filters.clear")), 1)]),
								_: 1
							})])
						]),
						_: 1
					}, 8, ["state"])]),
					_: 1
				}),
				C(se) ? (_(), u(x, {
					key: 0,
					color: "error",
					title: C(z)("keen.entries.load_failed"),
					"data-testid": "keen-entries-failed"
				}, null, 8, ["title"])) : C(J) && C(G).length === 0 ? (_(), u(w, {
					key: 1,
					class: "h-32"
				})) : (_(), u(y, { key: 2 }, {
					default: T(() => [h(e, { entries: C(G) }, null, 8, ["entries"])]),
					_: 1
				})),
				C(K) && (C(U).cursor || C(K).next_cursor) ? (_(), f("nav", {
					key: 3,
					class: "flex items-center justify-end gap-2",
					"aria-label": C(z)("keen.entries.pagination"),
					"data-testid": "keen-pages"
				}, [
					C(U).cursor ? (_(), u(l, {
						key: 0,
						color: "neutral",
						variant: "outline",
						size: "sm",
						"data-testid": "keen-page-newest",
						onClick: n[1] ||= (e) => $(null)
					}, {
						default: T(() => [m(S(C(z)("keen.entries.newest")), 1)]),
						_: 1
					})) : d("", !0),
					C(U).cursor && C(K).prev_cursor ? (_(), u(l, {
						key: 1,
						color: "neutral",
						variant: "outline",
						size: "sm",
						"data-testid": "keen-page-newer",
						onClick: n[2] ||= (e) => $(C(K).prev_cursor)
					}, {
						default: T(() => [m(S(C(z)("keen.entries.newer")), 1)]),
						_: 1
					})) : d("", !0),
					C(K).next_cursor ? (_(), u(l, {
						key: 2,
						color: "neutral",
						variant: "outline",
						size: "sm",
						"data-testid": "keen-page-older",
						onClick: n[3] ||= (e) => $(C(K).next_cursor)
					}, {
						default: T(() => [m(S(C(z)("keen.entries.older")), 1)]),
						_: 1
					})) : d("", !0)
				], 8, P)) : d("", !0)
			]));
		};
	}
});
export { F as t };
