(function () {
    "use strict";
    /*---------------------------------------------------------------------
        Fieldset
    -----------------------------------------------------------------------*/
    let currentTab = 0;

    const ActiveTab=(n)=>{
        if(n==0){
            document.getElementById("paso1").classList.add("active");
            document.getElementById("paso1").classList.remove("done");
            document.getElementById("paso2").classList.remove("done");
            document.getElementById("paso2").classList.remove("active");
            document.getElementById("paso3").classList.remove("done");
            document.getElementById("paso3").classList.remove("active");           
        }
        if(n==1){
            document.getElementById("paso1").classList.add("done");
            document.getElementById("paso1").classList.add("active");
            document.getElementById("paso2").classList.add("active");
            document.getElementById("paso2").classList.remove("done");
            document.getElementById("paso3").classList.remove("done");
            document.getElementById("paso3").classList.remove("active");

        }
        if(n==2){
            document.getElementById("paso1").classList.add("done");
            document.getElementById("paso1").classList.add("active");
            document.getElementById("paso2").classList.add("done");
            document.getElementById("paso2").classList.add("active");
            document.getElementById("paso3").classList.add("active");
            document.getElementById("paso3").classList.remove("done");
        }
        if(n==3){
            document.getElementById("paso1").classList.add("done");
            document.getElementById("paso1").classList.add("active");
            document.getElementById("paso2").classList.add("done");
            document.getElementById("paso2").classList.add("active");
            document.getElementById("paso3").classList.add("done");
            document.getElementById("paso3").classList.add("active");
        }
    }

    const showTab=(n)=>{
        var x = document.getElementsByClassName("formwizard_fieldset");
        x[n].style.display = "block";
        ActiveTab(n);
    }

    const nextBtnFunction=(n) => {
        var x = document.getElementsByClassName("formwizard_fieldset");
        x[currentTab].style.display = "none";
        currentTab = currentTab + n;
        showTab(currentTab);
    }

    const nextbtn = document.querySelectorAll('.next')
    Array.from(nextbtn, (nbtn) => {
    nbtn.addEventListener('click', function()
    {
        nextBtnFunction(1);
    })
});

// previousbutton

const prebtn = document.querySelectorAll('.previous')
    Array.from(prebtn, (pbtn) => {
    pbtn.addEventListener('click',function()
    {
        nextBtnFunction(-1);
    })
});

})(jQuery);