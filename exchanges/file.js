function Si(t) {
        var e = "00-" + Oi(32) + "-" + Oi(16) + "-01";
        try {
            e = function(t) {
                var e, n, r, o = Ei.config, i = "01", a = ((null == o || null == (e = o.operationSampling) ? void 0 : e.perOperationStrategies) || []).find((function(e) {
                    return t.includes(e.operation)
                }
                ));
                return i = a ? (null == a || null == (n = a.probabilisticSampling) ? void 0 : n.samplingRate) >= Math.random() ? "01" : "00" : (null == o || null == (r = o.probabilisticSampling) ? void 0 : r.samplingRate) >= Math.random() ? "01" : "00",
                "00-" + Oi(32) + "-" + Oi(16) + "-" + i
            }(t)
        } catch (t) {}
        return e
    }