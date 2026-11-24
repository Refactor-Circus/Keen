import { t as e } from "./KeenEntriesTable-DiAr2yww.js";
import { i as t, r as n, t as r } from "./entries-ofT4k6gi.js";
import { AtriumNoAccess as i, atriumStatus as a, integrationData as o, useAtriumIntegration as s } from "@refactor-circus/atrium";
import { Fragment as c, computed as l, createBlock as u, createCommentVNode as d, createElementBlock as f, createElementVNode as ee, createTextVNode as p, createVNode as m, defineComponent as h, isRef as g, normalizeClass as _, openBlock as v, reactive as y, ref as b, renderList as x, shallowRef as S, toDisplayString as C, unref as w, watch as T, withCtx as E } from "vue";
import { UAlert as te, UButton as ne, UCard as re, UForm as ie, UFormField as ae, UInput as oe, USelect as D, USkeleton as O } from "@refactor-circus/atrium/ui";
import { useI18n as se } from "vue-i18n";
import { useRoute as ce, useRouter as k } from "vue-router";
// src/components/KeenEntriesBrowser.vue?vue&type=script&setup=true&lang.ts
var A = {
	key: 1,
	class: "space-y-4"
}, j = { class: "flex gap-2 self-end" }, le = ["aria-label"], M = "*", N = /* @__PURE__ */ h({
	__name: "KeenEntriesBrowser",
	props: {
		source: {},
		sources: {}
	},
	setup(h, { expose: N }) {
		let P = h, F = ce(), I = k(), { t: L } = se(), { client: R } = s("keen"), z = {
			source: L("keen.filters.source"),
			action: L("keen.filters.action"),
			subject_type: L("keen.filters.subject_type"),
			subject_id: L("keen.filters.subject_id"),
			actor_type: L("keen.filters.actor_type"),
			actor_id: L("keen.filters.actor_id"),
			since: L("keen.filters.since"),
			until: L("keen.filters.until")
		}, B = r.filter((e) => e !== "source"), V = l(() => {
			let e = n(F.query);
			return P.source && (e.source = P.source), e;
		}), H = y(Object.fromEntries(r.map((e) => [e, ""])));
		T(V, (e) => {
			for (let t of r) H[t] = e[t] ?? "";
		}, { immediate: !0 });
		let U = l(() => [
			{
				value: M,
				label: L("keen.filters.all_sources")
			},
			...P.sources ?? [],
			{
				value: "app",
				label: L("keen.filters.source_app")
			}
		]), W = l({
			get: () => H.source === "" ? M : H.source,
			set: (e) => {
				H.source = e === M ? "" : e;
			}
		}), G = S([]), K = S(null), q = S(null), J = b(!1), Y = l(() => a(q.value) === 403), X = l(() => t(q.value)), ue = l(() => q.value !== null && !Y.value && Object.keys(X.value).length === 0);
		async function Z() {
			if (R) {
				J.value = !0, q.value = null;
				try {
					let e = o(await R.GET("/entries", { params: { query: V.value } }));
					G.value = e.data, K.value = e.meta;
				} catch (e) {
					q.value = e, G.value = [], K.value = null;
				} finally {
					J.value = !1;
				}
			}
		}
		T(() => JSON.stringify(V.value), () => void Z(), { immediate: !0 });
		function Q() {
			let e = { ...F.query };
			for (let t of [...r, "cursor"]) delete e[t];
			return e;
		}
		function de() {
			let e = {};
			for (let t of r) H[t] !== "" && !(t === "source" && P.source) && (e[t] = H[t]);
			I.replace({ query: {
				...Q(),
				...e
			} });
		}
		function fe() {
			I.replace({ query: Q() });
		}
		function $(e) {
			let t = { ...F.query };
			delete t.cursor, I.replace({ query: e ? {
				...t,
				cursor: e
			} : t });
		}
		return N({ reload: Z }), (t, n) => {
			let r = i, a = D, o = ae, s = oe, l = ne, y = ie, b = re, S = te, T = O;
			return w(Y) ? (v(), u(r, { key: 0 })) : (v(), f("div", A, [
				m(b, null, {
					default: E(() => [m(y, {
						state: w(H),
						class: "flex flex-wrap items-start gap-3",
						"data-testid": "keen-filters",
						onSubmit: de
					}, {
						default: E(() => [
							h.source ? d("", !0) : (v(), u(o, {
								key: 0,
								label: z.source,
								name: "source",
								error: w(X).source?.[0],
								class: "w-44"
							}, {
								default: E(() => [m(a, {
									modelValue: w(W),
									"onUpdate:modelValue": n[0] ||= (e) => g(W) ? W.value = e : null,
									items: w(U),
									class: "w-full",
									"data-testid": "keen-filter-source"
								}, null, 8, ["modelValue", "items"])]),
								_: 1
							}, 8, ["label", "error"])),
							(v(!0), f(c, null, x(w(B), (e) => (v(), u(o, {
								key: e,
								label: z[e],
								name: e,
								hint: e === "action" ? w(L)("keen.filters.action_hint") : void 0,
								error: w(X)[e]?.[0],
								class: _(e === "action" ? "w-56" : "w-44")
							}, {
								default: E(() => [m(s, {
									modelValue: w(H)[e],
									"onUpdate:modelValue": (t) => w(H)[e] = t,
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
							ee("div", j, [m(l, {
								type: "submit",
								icon: "i-lucide-funnel",
								"data-testid": "keen-filter-apply"
							}, {
								default: E(() => [p(C(w(L)("keen.filters.apply")), 1)]),
								_: 1
							}), m(l, {
								type: "button",
								icon: "i-lucide-x",
								color: "neutral",
								variant: "ghost",
								"data-testid": "keen-filter-clear",
								onClick: fe
							}, {
								default: E(() => [p(C(w(L)("keen.filters.clear")), 1)]),
								_: 1
							})])
						]),
						_: 1
					}, 8, ["state"])]),
					_: 1
				}),
				w(ue) ? (v(), u(S, {
					key: 0,
					color: "error",
					title: w(L)("keen.entries.load_failed"),
					"data-testid": "keen-entries-failed"
				}, null, 8, ["title"])) : w(J) && w(G).length === 0 ? (v(), u(T, {
					key: 1,
					class: "h-32"
				})) : (v(), u(b, { key: 2 }, {
					default: E(() => [m(e, { entries: w(G) }, null, 8, ["entries"])]),
					_: 1
				})),
				w(K) && (w(V).cursor || w(K).next_cursor) ? (v(), f("nav", {
					key: 3,
					class: "flex items-center justify-end gap-2",
					"aria-label": w(L)("keen.entries.pagination"),
					"data-testid": "keen-pages"
				}, [
					w(V).cursor ? (v(), u(l, {
						key: 0,
						color: "neutral",
						variant: "outline",
						size: "sm",
						"data-testid": "keen-page-newest",
						onClick: n[1] ||= (e) => $(null)
					}, {
						default: E(() => [p(C(w(L)("keen.entries.newest")), 1)]),
						_: 1
					})) : d("", !0),
					w(V).cursor && w(K).prev_cursor ? (v(), u(l, {
						key: 1,
						color: "neutral",
						variant: "outline",
						size: "sm",
						"data-testid": "keen-page-newer",
						onClick: n[2] ||= (e) => $(w(K).prev_cursor)
					}, {
						default: E(() => [p(C(w(L)("keen.entries.newer")), 1)]),
						_: 1
					})) : d("", !0),
					w(K).next_cursor ? (v(), u(l, {
						key: 2,
						color: "neutral",
						variant: "outline",
						size: "sm",
						"data-testid": "keen-page-older",
						onClick: n[3] ||= (e) => $(w(K).next_cursor)
					}, {
						default: E(() => [p(C(w(L)("keen.entries.older")), 1)]),
						_: 1
					})) : d("", !0)
				], 8, le)) : d("", !0)
			]));
		};
	}
});
export { N as t };
